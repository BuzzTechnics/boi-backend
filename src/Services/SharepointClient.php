<?php

namespace Boi\Backend\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Transport for submitting application payloads to the SharePoint / Power
 * Automate workflow. Program-specific payload building (template + overlays)
 * stays in each intervention app; this client owns the shared mechanics:
 * payload normalisation, encoding, the HTTP call + error handling, and
 * workflow-id extraction.
 */
class SharepointClient
{
    /**
     * Power Automate chokes on \r\n — collapse carriage returns across every
     * string value (round-trips through JSON to reach nested values).
     */
    public static function normalizePayload(array $payload): array
    {
        return json_decode(str_replace('\r\n', '\n', json_encode($payload)), true);
    }

    /** Encode the way Power Automate expects: no escaped slashes/unicode, preserve .0 floats. */
    public static function encode(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * POST an already-encoded payload to the workflow URL.
     *
     * Returns a structured result the caller persists against its model:
     *   success → ['ok' => true, 'response' => mixed]
     *   failure → ['ok' => false, 'error' => string, 'status' => int|null, 'body' => string|null]
     *
     * @param  array<string, mixed>  $logContext  Merged into the failure log (e.g. application_id, url).
     * @return array{ok: bool, response?: mixed, error?: string, status?: int|null, body?: string|null}
     */
    public static function submit(string $jsonPayload, string $url, ?string $key, array $logContext = []): array
    {
        if (! app()->environment('production')) {
            Log::info('--- SharePoint cURL Start ---');
            Log::info('curl -X POST \\');
            Log::info("  '{$url}' \\");
            Log::info("  -H 'Content-Type: application/json' \\");
            Log::info("  -H 'Key: {$key}' \\");
            Log::info("  -d '{$jsonPayload}'");
            Log::info('--- SharePoint cURL End ---');
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Key' => $key,
            ])
                ->timeout(30)
                ->withBody($jsonPayload, 'application/json')
                ->post($url)
                ->throw()
                ->json();

            return ['ok' => true, 'response' => $response];
        } catch (RequestException $e) {
            $res = $e->response;
            $body = $res?->body();

            Log::error('SharePoint application submit failed', $logContext + [
                'status' => $res?->status(),
                'error' => $e->getMessage(),
                'body' => $body ? substr($body, 0, 5000) : null,
            ]);

            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'status' => $res?->status(),
                'body' => $body ? substr($body, 0, 5000) : null,
            ];
        } catch (\Throwable $e) {
            Log::error('SharePoint application submit failed', $logContext + ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Pull the Power Automate workflow id out of a response, tolerating its several shapes. */
    public static function extractWorkflowId(array $response): ?string
    {
        $workflowId = data_get($response, 'workflowId')
            ?? data_get($response, 'workflow_id')
            ?? data_get($response, 'error.workflowId')
            ?? data_get($response, 'error.workflow_id')
            ?? data_get($response, 'data.workflowId')
            ?? data_get($response, 'data.workflow_id');

        $workflowId = $workflowId === null ? null : trim((string) $workflowId);

        if (! $workflowId || strtolower($workflowId) === 'null') {
            return null;
        }

        return $workflowId;
    }
}
