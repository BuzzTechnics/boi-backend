<?php

namespace Boi\Backend\Models\Concerns;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches the EDOC consolidated-metrics PDF for an application via boi-api and
 * stores the returned path on the model's `bank_statement_analysis` column.
 *
 * boi-api loads the application's bank statements (by id + app slug), calls
 * EDOC, stores the PDF and returns its path. No JSON body is sent; auth is the
 * bearer `boi_proxy.key` plus the `X-Boi-User` / `X-Boi-App` headers.
 *
 * The using model must expose `id` and `user_id`.
 */
trait ConsolidatesBankStatements
{
    /**
     * @return array{success: bool, message: string, path: ?string}
     */
    public function edocConsolidate(): array
    {
        $base = rtrim((string) config('boi_proxy.url'), '/');
        $key = (string) config('boi_proxy.key');
        if ($base === '' || $key === '') {
            Log::info('edocConsolidate: skipped (BOI_API_URL / BOI_API_KEY not set)', [
                'application_id' => $this->id,
            ]);

            return [
                'success' => false,
                'message' => 'BOI API URL or key is not configured.',
                'path' => null,
            ];
        }

        $segment = (string) config('boi_proxy.bank_statements_path', 'loan-applications');
        $url = $base.'/api/'.$segment.'/'.$this->id.'/bank-statements/consolidated-metrics';

        try {
            $response = Http::withToken($key)
                ->withHeaders([
                    (string) config('boi_proxy.user_header', 'X-Boi-User') => (string) $this->user_id,
                    (string) config('boi_proxy.app_header', 'X-Boi-App') => (string) config('boi_proxy.app', 'app'),
                ])
                ->acceptJson()
                ->timeout(300)
                ->post($url);

            if (! $response->successful()) {
                Log::warning('edocConsolidate: boi-api error', [
                    'application_id' => $this->id,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 2000),
                ]);

                // boi-api already maps eDoc failures to a customer-facing message;
                // surface it rather than a bare HTTP status.
                $apiMessage = $response->json('message');

                return [
                    'success' => false,
                    'message' => is_string($apiMessage) && trim($apiMessage) !== ''
                        ? $apiMessage
                        : 'We could not process your bank statements. Please try again.',
                    'path' => null,
                ];
            }

            $json = $response->json();
            if (! is_array($json) || empty($json['success'])) {
                Log::warning('edocConsolidate: unexpected response', [
                    'application_id' => $this->id,
                    'json' => $json,
                ]);

                $apiMessage = is_array($json) && isset($json['message']) && is_string($json['message'])
                    ? trim($json['message'])
                    : '';

                return [
                    'success' => false,
                    'message' => $apiMessage !== ''
                        ? $apiMessage
                        : 'We could not process your bank statements. Please try again.',
                    'path' => null,
                ];
            }

            $data = $json['data'] ?? [];
            if (! is_array($data) || ! empty($data['skipped']) || empty($data['path'])) {
                $reason = is_array($data) && ! empty($data['reason']) ? (string) $data['reason'] : 'skipped or no path';

                return [
                    'success' => false,
                    'message' => 'No PDF path ( '.$reason.' ).',
                    'path' => null,
                ];
            }

            $path = (string) $data['path'];
            $this->bank_statement_analysis = $path;
            $this->saveQuietly();

            return [
                'success' => true,
                'message' => 'Consolidated PDF saved.',
                'path' => $path,
            ];
        } catch (\Throwable $e) {
            Log::warning('edocConsolidate: exception', [
                'application_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'path' => null,
            ];
        }
    }
}
