<?php

namespace Boi\Backend\Sla\Support;

use Boi\Backend\Sla\Models\SlaDefinition;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves what a case type's SLA actually is: the database row if BOI has set one,
 * otherwise the value the portal shipped with.
 *
 * That order is deliberate. A fund goes live on the numbers in its repository, where
 * they are reviewable and versioned; the table exists so BOI can change them
 * afterwards without a deploy. A portal that never seeds the table behaves exactly
 * as though this class were config lookup, which is how each one starts.
 *
 * Definitions are read once per request — the engine asks for the same handful of
 * case types on every tracker it walks.
 */
class SlaDefinitions
{
    /** @var array<string, SlaDefinition|null> */
    private array $cache = [];

    public function app(): ?string
    {
        $app = config('boi_sla.app');

        return is_string($app) && $app !== '' ? $app : null;
    }

    public function stored(string $caseType): ?SlaDefinition
    {
        if (array_key_exists($caseType, $this->cache)) {
            return $this->cache[$caseType];
        }

        // A portal may adopt the engine from config alone and never run the
        // migration; asking for a missing table should not be fatal.
        if (! Schema::hasTable('boi_sla_definitions')) {
            return $this->cache[$caseType] = null;
        }

        return $this->cache[$caseType] = SlaDefinition::query()
            ->forApp($this->app())
            ->where('case_type', $caseType)
            ->where('is_active', true)
            // A row naming this portal beats a shared one.
            ->orderByRaw('case when app is null then 1 else 0 end')
            ->first();
    }

    /** @return array<string, mixed> */
    public function fallback(string $caseType): array
    {
        return (array) config("boi_sla.definitions.{$caseType}", []);
    }

    public function name(string $caseType): string
    {
        return (string) ($this->stored($caseType)?->name
            ?? $this->fallback($caseType)['name']
            ?? ucwords(str_replace('_', ' ', $caseType)));
    }

    /** Working minutes allowed for the clock of the given type. */
    public function minutes(string $caseType, string $trackerType): int
    {
        $stored = $this->stored($caseType);
        $fallback = $this->fallback($caseType);

        if ($trackerType === 'assignment') {
            return (int) ($stored?->assignment_minutes ?? $fallback['assignment_minutes'] ?? 0);
        }

        return (int) ($stored?->sla_minutes ?? $fallback['minutes'] ?? 0);
    }

    /**
     * Role names for a slot ('owner_roles', 'level_1_roles', …).
     *
     * @return array<int, string>
     */
    public function rolesFor(string $caseType, string $slot): array
    {
        $stored = $this->stored($caseType)?->{$slot};

        if (is_array($stored) && $stored !== []) {
            return array_values(array_filter(array_map('strval', $stored)));
        }

        $fallback = $this->fallback($caseType)[$slot] ?? [];

        return is_array($fallback) ? array_values(array_filter(array_map('strval', $fallback))) : [];
    }

    /**
     * The fractions at which each level fires, for this case type.
     *
     * @return array<string, float>
     */
    public function thresholds(string $caseType): array
    {
        $defaults = (array) config('boi_sla.thresholds', []);
        $overrides = $this->stored($caseType)?->thresholds
            ?? $this->fallback($caseType)['thresholds']
            ?? [];

        $merged = array_merge($defaults, is_array($overrides) ? $overrides : []);

        // A level set to null is one this portal does not use — the online portal
        // stops at level 2 for its own queues and keeps 3 for SharePoint.
        return array_map(
            fn ($fraction) => (float) $fraction,
            array_filter($merged, fn ($fraction) => $fraction !== null && (float) $fraction > 0)
        );
    }

    public function forget(): void
    {
        $this->cache = [];
    }
}
