<?php

namespace Boi\Backend\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Posts a SharePoint JSON payload either via boi-api ({@see BoiIntegrationsClient}) or directly using host {@see config('services.sharepoint')}.
 * Webhooks remain on the consuming app (e.g. Glow).
 */
final class SharePointGateway
{
    /**
     * @param  array<string, mixed>  $payload  Body sent to SharePoint (same shape as today’s direct POST).
     */
    public static function post(array $payload): Response
    {
        $timeout = (int) config('boi_integrations.sharepoint.timeout', 300);
        $retries = (int) config('boi_integrations.sharepoint.retry_times', 2);
        $delayMs = (int) config('boi_integrations.sharepoint.retry_delay_ms', 1000);

        if ($http = BoiIntegrationsClient::http()) {
            return $http->asJson()
                ->timeout($timeout)
                ->retry($retries, $delayMs)
                ->post('/api/integrations/sharepoint/submit', ['payload' => $payload]);
        }

        $url = (string) config('services.sharepoint.url', '');
        $key = config('services.sharepoint.key');
        if ($url === '' || $key === null || $key === '') {
            throw new \RuntimeException('SharePoint is not configured (services.sharepoint.url and services.sharepoint.key).');
        }

        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Key' => (string) $key,
        ])
            ->timeout($timeout)
            ->retry($retries, $delayMs)
            ->post($url, $payload);
    }
}
