<?php

namespace Boi\Backend\Services;

use Boi\Backend\Models\EdocCall;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Wraps an outbound Edoc HTTP call, persists one row to the `edoc_calls` table
 * with timing, status, and PII-scrubbed payload, and re-throws the original
 * exception untouched. Designed to slot in around the existing Http::* call
 * sites in each project's Edoc service without changing public signatures.
 *
 * Example:
 *   return EdocCallLogger::record(
 *       endpoint: $url,
 *       method: 'POST',
 *       payload: ['email' => $email, 'firstName' => $firstName],
 *       call: fn () => Http::withHeaders(self::headers())->post($url, $body),
 *   );
 */
class EdocCallLogger
{
    /**
     * Sensitive top-level / nested keys whose values are replaced before persistence.
     */
    private const SENSITIVE_KEYS = [
        'bvn',
        'nin',
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'client_secret',
        'otp',
        'account_number',
        'card_number',
        'authorization',
    ];

    private const REDACTED = '[REDACTED]';

    private const RESPONSE_BODY_LIMIT_BYTES = 65536;

    /**
     * @param  callable(): Response  $call
     * @param  array<string, mixed>|null  $payload
     */
    public static function record(
        string $endpoint,
        string $method,
        ?array $payload,
        callable $call,
        ?int $userId = null,
    ): Response {
        // Scope: only the eDoc "get transactions" endpoint is persisted to
        // edoc_calls. Every other eDoc call still runs normally — it just
        // isn't recorded — so the audit trail stays focused on transactions.
        if (! self::isRecordableEndpoint($endpoint)) {
            return $call();
        }

        $start = microtime(true);
        $row = [
            'project' => self::projectName(),
            'method' => strtoupper($method),
            'endpoint' => mb_substr($endpoint, 0, 1024),
            'request_payload' => self::scrub($payload),
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
            EdocCall::query()->create($row);
        } catch (Throwable $e) {
            // Persisting the audit row must never break the underlying Edoc call;
            // log and swallow — the Edoc response is what callers care about.
            if (function_exists('logger')) {
                logger()->warning('EdocCallLogger: failed to persist edoc_calls row', [
                    'error' => $e->getMessage(),
                    'endpoint' => $row['endpoint'] ?? null,
                ]);
            }
        }
    }

    /**
     * Only the eDoc "get transactions" endpoint
     * (/v1/external/consent/{consentId}/transactions) is recorded.
     */
    private static function isRecordableEndpoint(string $endpoint): bool
    {
        $path = strtok($endpoint, '?');

        return is_string($path) && str_ends_with(rtrim($path, '/'), '/transactions');
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
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private static function scrub(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        return self::scrubArray($payload);
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = self::REDACTED;

                continue;
            }
            if (is_array($value)) {
                $data[$key] = self::scrubArray($value);
            }
        }

        return $data;
    }

    /**
     * Returns the response body as an array (preferred for the JSON column)
     * or a wrapper indicating truncation when the payload is too large.
     */
    private static function truncateBody(Response $response): mixed
    {
        $raw = $response->body();
        if (strlen($raw) > self::RESPONSE_BODY_LIMIT_BYTES) {
            return [
                '_truncated' => true,
                '_size_bytes' => strlen($raw),
                'preview' => mb_substr($raw, 0, 8192),
            ];
        }

        $json = $response->json();
        if (is_array($json)) {
            return self::scrubArray($json);
        }

        return ['raw' => $raw];
    }
}
