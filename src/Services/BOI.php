<?php

namespace Boi\Backend\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * BOI enterprise third-party API: authentication, BVN/NIN checks, CAC verification.
 */
class BOI
{
    private const CACHE_KEY = 'boi_backend:boi_thirdparty_api_token';

    private const CAC_CACHE_KEY = 'boi_backend:boi_cac_verify_token';

    public static function getToken(): string
    {
        $cachedToken = Cache::get(self::CACHE_KEY);

        if ($cachedToken !== null && self::isValidToken($cachedToken)) {
            return $cachedToken;
        }

        $base = config('boi_integrations.boi_thirdparty.api_base_url');
        $username = config('boi_integrations.boi_thirdparty.username')
            ?? config('boi_integrations.boi_thirdparty.username_prod');
        $password = config('boi_integrations.boi_thirdparty.password')
            ?? config('boi_integrations.boi_thirdparty.password_prod');

        $token = Http::withHeaders([
            'access-control-allow-origin' => '*',
            'content-type' => 'application/json',
        ])
            ->timeout((int) config('boi_integrations.boi_thirdparty.http_timeout', 120))
            ->post($base.'/api/Authentication/Authenticate', [
                'emailOrUserName' => $username,
                'password' => $password,
            ])
            ->throw()
            ->body();

        $hours = (int) config('boi_integrations.boi_thirdparty.token_cache_ttl_hours', 12);
        Cache::put(self::CACHE_KEY, $token, now()->addHours(max(1, $hours)));

        return $token;
    }

    /**
     * The CAC verification gateway runs its own auth at /Authentication/Authenticate
     * with field name `username` (not `emailOrUserName`) and returns
     * `{"token", "success", ...}`. Same prod credentials as the Rubikon API.
     */
    public static function getCacToken(): string
    {
        $cachedToken = Cache::get(self::CAC_CACHE_KEY);

        if ($cachedToken !== null && self::isValidToken($cachedToken)) {
            return $cachedToken;
        }

        $base = config('boi_integrations.boi_thirdparty.cac_verify_base_url');
        $username = config('boi_integrations.boi_thirdparty.username_prod')
            ?? config('boi_integrations.boi_thirdparty.username');
        $password = config('boi_integrations.boi_thirdparty.password_prod')
            ?? config('boi_integrations.boi_thirdparty.password');

        $response = Http::timeout((int) config('boi_integrations.boi_thirdparty.http_timeout', 120))
            ->connectTimeout(10)
            ->withHeaders(['accept' => '*/*'])
            ->asJson()
            ->post($base.'/Authentication/Authenticate', [
                'username' => $username,
                'password' => $password,
            ])
            ->throw()
            ->json();

        if (empty($response['token'])) {
            throw new \Exception('CAC authentication failed: '.($response['message'] ?? 'no token'));
        }

        $token = trim((string) $response['token']);

        $hours = (int) config('boi_integrations.boi_thirdparty.token_cache_ttl_hours', 12);
        Cache::put(self::CAC_CACHE_KEY, $token, now()->addHours(max(1, $hours)));

        return $token;
    }

    private static function isValidToken(string $token): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_=]+$/', $token);
    }

    /**
     * @return array<string, mixed>
     */
    public static function customerBvn(string $bvn): array
    {
        try {
            $base = config('boi_integrations.boi_thirdparty.api_base_url');
            $url = $base.'/api/ThirdPartyAPI/CheckCustomerBVN?bvn='.$bvn;
            $response = BvnNinCallLogger::record(
                kind: 'bvn',
                identifier: $bvn,
                endpoint: $url,
                method: 'POST',
                payload: null,
                call: fn () => Http::timeout((int) config('boi_integrations.boi_thirdparty.http_timeout', 120))
                    ->withHeaders([
                        'Authorization' => 'Bearer '.self::getToken(),
                        'Content-Type' => 'application/json',
                    ])
                    ->post($url),
            )->throw()->json();

            if (isset($response['message']) && $response['message'] != 'Successful') {
                $response['message'] = 'validation failed. no record found for this number ';
            }

            return $response;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function customerNin(string $nin): array
    {
        try {
            $base = config('boi_integrations.boi_thirdparty.api_base_url');
            $url = $base.'/api/ThirdPartyAPI/CheckCustomerNIN?nin='.$nin;
            $response = BvnNinCallLogger::record(
                kind: 'nin',
                identifier: $nin,
                endpoint: $url,
                method: 'POST',
                payload: null,
                call: fn () => Http::timeout((int) config('boi_integrations.boi_thirdparty.http_timeout', 120))
                    ->withHeaders([
                        'Authorization' => 'Bearer '.self::getToken(),
                        'Content-Type' => 'application/json',
                    ])
                    ->post($url),
            )->throw()->json();

            if (isset($response['message']) && $response['message'] != 'Successful') {
                $response['message'] = 'validation failed. no record found for this number ';
            }

            return $response;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function businessCac(string $rcNumber): array
    {
        $cacBase = config('boi_integrations.boi_thirdparty.cac_verify_base_url');
        $timeout = (int) config('boi_integrations.boi_thirdparty.http_timeout', 120);

        $call = static function () use ($cacBase, $rcNumber, $timeout): array {
            // CAC gateway returns 401 with WWW-Authenticate: Bearer for
            // unauthenticated calls — we have to send the CAC-specific token.
            $response = Http::timeout($timeout)
                ->connectTimeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer '.self::getCacToken(),
                    'accept' => '*/*',
                ])
                ->asJson()
                ->post($cacBase.'/Verification/verify-business/'.$rcNumber, []);

            if ($response->status() === 401) {
                // Force the catch below to invalidate the cached token and retry.
                $response->throw();
            }

            $response->throw();

            $body = $response->json();

            // The API echoes the input RC back as `registrationNumber` even
            // when status is "not_found", so the only reliable hit signal is
            // status === "found" AND a non-empty name.
            if (($body['status'] ?? null) !== 'found' || empty($body['name'])) {
                throw new \Exception('Invalid RC number - no company found');
            }

            return $body;
        };

        try {
            return $call();
        } catch (RequestException $e) {
            if ($e->response !== null && $e->response->status() === 401) {
                Cache::forget(self::CAC_CACHE_KEY);

                return $call();
            }

            throw $e;
        }
    }
}
