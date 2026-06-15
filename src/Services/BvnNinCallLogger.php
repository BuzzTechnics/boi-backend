<?php

namespace Boi\Backend\Services;

use Boi\Backend\Models\BvnNinCall;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Wraps an outbound BVN/NIN verification call and persists one row to the
 * `bvn_nin_calls` table with timing, status, and PII-safe data, then re-throws
 * any exception untouched. Slots in around BOI::customerBvn/customerNin without
 * changing their signatures.
 *
 * PII policy (mask + scrub):
 *  - The raw BVN/NIN is NEVER stored — only a masked form (e.g. ******7890),
 *    and any bvn=/nin= query value in the endpoint is masked too.
 *  - The response is whitelisted to identity-summary fields; everything else
 *    is redacted.
 *
 * Example:
 *   return BvnNinCallLogger::record(
 *       kind: 'bvn',
 *       identifier: $bvn,
 *       endpoint: $url,
 *       method: 'POST',
 *       payload: null,
 *       call: fn () => Http::withHeaders($h)->post($url),
 *   );
 */
class BvnNinCallLogger
{
    /** Response keys that are safe to retain; all others are redacted. */
    private const ALLOWED_RESPONSE_KEYS = [
        'message',
        'status',
        'statusCode',
        'code',
        'success',
        'firstName',
        'middleName',
        'lastName',
        'dateOfBirth',
        'dob',
        'gender',
    ];

    private const REDACTED = '[REDACTED]';

    private const RESPONSE_BODY_LIMIT_BYTES = 65536;

    /**
     * @param  'bvn'|'nin'  $kind
     * @param  callable(): Response  $call
     * @param  array<string, mixed>|null  $payload
     */
    public static function record(
        string $kind,
        ?string $identifier,
        string $endpoint,
        string $method,
        ?array $payload,
        callable $call,
        ?int $userId = null,
    ): Response {
        $start = microtime(true);
        $masked = self::mask($identifier);
        $row = [
            'project' => self::projectName(),
            'kind' => strtolower($kind),
            'identifier_masked' => $masked,
            'method' => strtoupper($method),
            'endpoint' => mb_substr(self::sanitizeEndpoint($endpoint, $identifier, $masked), 0, 1024),
            'request_payload' => self::whitelist($payload),
            'user_id' => $userId ?? self::currentUserId(),
            'created_at' => now(),
        ];

        try {
            $response = $call();
            $row['response_status'] = $response->status();
            $row['response_body'] = self::truncateBody($response);
            $row['duration_ms'] = self::elapsedMs($start);
            $row['succeeded'] = $response->successful();
            self::persist($row);

            return $response;
        } catch (Throwable $e) {
            $row['exception_class'] = mb_substr(get_class($e), 0, 255);
            $row['response_body'] = ['error' => mb_substr($e->getMessage(), 0, 1000)];
            $row['duration_ms'] = self::elapsedMs($start);
            $row['succeeded'] = false;
            self::persist($row);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function persist(array $row): void
    {
        try {
            BvnNinCall::query()->create($row);
        } catch (Throwable $e) {
            // The audit row must never break the underlying verification call.
            if (function_exists('logger')) {
                logger()->warning('BvnNinCallLogger: failed to persist bvn_nin_calls row', [
                    'error' => $e->getMessage(),
                    'kind' => $row['kind'] ?? null,
                ]);
            }
        }
    }

    private static function mask(?string $identifier): ?string
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }
        $len = mb_strlen($identifier);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', $len - 4).mb_substr($identifier, -4);
    }

    /**
     * Replace any raw bvn=/nin= query value (and any literal occurrence of the
     * identifier) in the endpoint with the masked form.
     */
    private static function sanitizeEndpoint(string $endpoint, ?string $identifier, ?string $masked): string
    {
        $endpoint = (string) preg_replace_callback(
            '/((?:bvn|nin)=)([^&]+)/i',
            fn ($m) => $m[1].($masked ?? self::REDACTED),
            $endpoint
        );

        if ($identifier !== null && $identifier !== '' && $masked !== null) {
            $endpoint = str_replace($identifier, $masked, $endpoint);
        }

        return $endpoint;
    }

    private static function projectName(): string
    {
        $configured = config('boi_backend.project');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return (string) (config('app.name') ?? 'unknown');
    }

    private static function currentUserId(): ?int
    {
        if (! function_exists('auth')) {
            return null;
        }
        $id = auth()->id();

        return is_numeric($id) ? (int) $id : null;
    }

    private static function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }

    /**
     * Keep only whitelisted top-level keys; redact every other value.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>|null
     */
    private static function whitelist(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $out = [];
        foreach ($data as $key => $value) {
            $out[$key] = in_array((string) $key, self::ALLOWED_RESPONSE_KEYS, true)
                ? $value
                : self::REDACTED;
        }

        return $out;
    }

    private static function truncateBody(Response $response): mixed
    {
        $raw = $response->body();
        if (strlen($raw) > self::RESPONSE_BODY_LIMIT_BYTES) {
            return ['_truncated' => true, '_size_bytes' => strlen($raw)];
        }

        $json = $response->json();
        if (is_array($json)) {
            return self::whitelist($json);
        }

        return ['raw' => self::REDACTED];
    }
}
