<?php

namespace Boi\Backend\Support;

use Boi\Backend\Services\BOI;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Reusable staff lookup backed by BOI's Active Directory.
 *
 * AD is the authoritative internal-staff list, so a fund can provision its admin
 * users (Project Officers, Group Heads / Supervisors, etc.) from it instead of a
 * hand-maintained spreadsheet. This is the shared, fund-agnostic core: the raw AD
 * search ({@see BOI::searchUsersActiveDirectory()}), normalisation to a stable
 * shape, filtering out the service/machine/security accounts AD also returns, and
 * a config-driven job-title → app-role suggestion.
 *
 * It is deliberately list-pull only — it never creates users and never revokes a
 * role. The consuming app decides how to provision a picked staff member (create a
 * user, assign the role) and thus keeps ownership of its own User model.
 */
class ActiveDirectoryStaff
{
    /** Marks the duplicate security-account twin AD keeps alongside a real person. */
    private const SECURITY_ACCOUNT_EMPLOYEE_ID = 'SECURITY ACCOUNT';

    /**
     * Search AD for assignable staff, normalised and sorted by name.
     *
     * Returns [] when the term is too short or AD is unreachable (logged), so a
     * caller can treat "no results" and "AD down" the same way in the UI.
     *
     * @return array<int, array{sam_account_name: string, name: string, email: string, employee_id: ?string, department: ?string, phone: ?string, manager_username: ?string, manager_email: ?string, role: ?string, suggested_role: ?string}>
     */
    public static function search(string $term, ?int $limit = null): array
    {
        $term = trim($term);
        $min = (int) config('boi_integrations.active_directory.search_min_length', 3);
        $limit = $limit ?? (int) config('boi_integrations.active_directory.default_limit', 25);

        if (Str::length($term) < max(1, $min)) {
            return [];
        }

        $ttl = (int) config('boi_integrations.active_directory.search_cache_ttl_minutes', 10);
        $cacheKey = 'boi_backend:ad_staff_search:'.md5(Str::lower($term));

        $results = Cache::remember($cacheKey, now()->addMinutes(max(1, $ttl)), function () use ($term) {
            try {
                return self::normalize(BOI::searchUsersActiveDirectory($term));
            } catch (\Throwable $e) {
                Log::warning('Active Directory staff search failed', [
                    'term' => $term,
                    'error' => $e->getMessage(),
                ]);

                // Don't cache a failure — the next keystroke should retry AD.
                return null;
            }
        });

        if ($results === null) {
            Cache::forget($cacheKey);

            return [];
        }

        return array_slice($results, 0, max(1, $limit));
    }

    /**
     * Reduce raw AD entries to assignable staff, deduped by email and sorted by name.
     * Each entry carries `suggested_role` — the app role its AD job title maps to,
     * or null when AD has no opinion.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    public static function normalize(array $entries): array
    {
        $staff = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $email = trim((string) ($entry['email'] ?? ''));
            $name = trim((string) ($entry['displayName'] ?? ''));
            $sam = trim((string) ($entry['samAccountName'] ?? ''));
            $employeeId = trim((string) ($entry['employeeId'] ?? ''));

            if (! self::isAssignable($sam, $name, $email, $employeeId)) {
                continue;
            }

            $role = self::nullableString($entry['role'] ?? null);

            // AD occasionally lists the same person under two accounts; the email is
            // the identity we provision and notify against, so key on it.
            $staff[Str::lower($email)] = [
                'sam_account_name' => $sam,
                'name' => $name,
                'email' => $email,
                'employee_id' => $employeeId !== '' ? $employeeId : null,
                'department' => self::nullableString($entry['department'] ?? null),
                'phone' => self::nullableString($entry['mobileNumber'] ?? null),
                'manager_username' => self::nullableString($entry['managerUsername'] ?? null),
                'manager_email' => self::nullableString($entry['managerEmail'] ?? null),
                // AD's free-text job title ("PROJECT OFFICER", "GROUP HEAD", ...).
                'role' => $role,
                'suggested_role' => self::roleNameFor($role),
            ];
        }

        $staff = array_values($staff);

        usort($staff, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $staff;
    }

    /**
     * Which app role an AD job title maps to, or null for "AD has no opinion".
     *
     * Driven by config `active_directory.role_map` (ordered; first matching role
     * wins), so each fund maps titles to its own role names. Returning null NEVER
     * means "revoke" — provisioning is additive.
     */
    public static function roleNameFor(?string $title): ?string
    {
        $title = Str::lower(trim((string) $title));
        $title = preg_replace('/\s+/', ' ', $title) ?? $title;

        if ($title === '') {
            return null;
        }

        $map = (array) config('boi_integrations.active_directory.role_map', []);

        foreach ($map as $entry) {
            $role = (string) ($entry['role'] ?? '');
            $patterns = (array) ($entry['patterns'] ?? []);
            if ($role === '') {
                continue;
            }
            foreach ($patterns as $pattern) {
                if (@preg_match($pattern, $title) === 1) {
                    return $role;
                }
            }
        }

        return null;
    }

    /**
     * A directory entry is assignable only if it is a person we can notify —
     * excludes Exchange system mailboxes, machine/service accounts and the
     * duplicate "[Security Account]" twin AD keeps beside a real person.
     */
    public static function isAssignable(string $sam, string $name, string $email, string $employeeId): bool
    {
        if ($name === '' || $email === '' || $sam === '') {
            return false;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (Str::contains($email, ['SystemMailbox', 'DiscoverySearchMailbox'], ignoreCase: true)) {
            return false;
        }

        if (Str::startsWith($sam, '$') || Str::endsWith($sam, '$')) {
            return false;
        }

        if (Str::upper($employeeId) === self::SECURITY_ACCOUNT_EMPLOYEE_ID) {
            return false;
        }

        if (Str::contains($name, '[Security Account]', ignoreCase: true)) {
            return false;
        }

        return true;
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
