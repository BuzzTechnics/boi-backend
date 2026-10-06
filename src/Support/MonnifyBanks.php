<?php

namespace Boi\Backend\Support;

use Boi\Backend\Support\Concerns\SyncsBankList;
use Illuminate\Support\Facades\Http;

/**
 * Syncs Nigerian banks from Monnify (Moniepoint) into an Eloquent model
 * (name, code, short_name). Unlike Paystack, Monnify requires authentication,
 * so credentials come from config('banks.monnify') (api_key, secret_key,
 * base_url), which default to the MONNIFY_* env vars.
 */
class MonnifyBanks
{
    use SyncsBankList;

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $bankModel
     * @param  callable(string):void|null  $onError
     */
    public static function sync(string $bankModel, ?callable $onError = null): bool
    {
        if (! class_exists($bankModel)) {
            $onError !== null && $onError("Bank model class does not exist: {$bankModel}");

            return false;
        }

        $config = config('banks.monnify', []);
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.monnify.com'), '/');
        $apiKey = $config['api_key'] ?? null;
        $secretKey = $config['secret_key'] ?? null;

        if (empty($apiKey) || empty($secretKey)) {
            $onError !== null && $onError('Monnify credentials are not configured (banks.monnify.api_key / secret_key).');

            return false;
        }

        $auth = Http::withHeaders([
            'Authorization' => 'Basic '.base64_encode($apiKey.':'.$secretKey),
        ])->timeout(30)->post($baseUrl.'/api/v1/auth/login');

        if (! $auth->successful() || ! $auth->json('requestSuccessful')) {
            $onError !== null && $onError('Monnify authentication failed: '.$auth->json('responseMessage', (string) $auth->status()));

            return false;
        }

        $token = $auth->json('responseBody.accessToken');
        if (empty($token)) {
            $onError !== null && $onError('Monnify did not return an access token.');

            return false;
        }

        $response = Http::withToken($token)->timeout(30)->get($baseUrl.'/api/v1/banks');

        if (! $response->successful() || ! $response->json('requestSuccessful')) {
            $onError !== null && $onError('Failed to fetch banks from Monnify: '.$response->json('responseMessage', (string) $response->status()));

            return false;
        }

        $body = $response->json('responseBody', []);
        if (! is_array($body)) {
            $onError !== null && $onError('Invalid Monnify response: expected "responseBody" array.');

            return false;
        }

        $banks = [];
        foreach ($body as $bank) {
            $code = $bank['code'] ?? null;
            $name = $bank['name'] ?? null;
            if ($code === null || $name === null || trim((string) $name) === '') {
                continue;
            }
            $banks[] = ['code' => (string) $code, 'name' => trim((string) $name)];
        }

        self::upsertBanks($bankModel, $banks);

        return true;
    }
}
