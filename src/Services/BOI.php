<?php

namespace Boi\Backend\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * BOI enterprise third-party API: authentication, BVN/NIN checks, CAC verification.
 */
class BOI
{
    private const CACHE_KEY = 'boi_backend:boi_thirdparty_api_token';

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
            $response = Http::timeout((int) config('boi_integrations.boi_thirdparty.http_timeout', 120))
                ->withHeaders([
                    'Authorization' => 'Bearer '.self::getToken(),
                    'Content-Type' => 'application/json',
                ])
                ->post($base.'/api/ThirdPartyAPI/CheckCustomerBVN?bvn='.$bvn)
                ->throw()
                ->json();

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
            $response = Http::timeout((int) config('boi_integrations.boi_thirdparty.http_timeout', 120))
                ->withHeaders([
                    'Authorization' => 'Bearer '.self::getToken(),
                    'Content-Type' => 'application/json',
                ])
                ->post($base.'/api/ThirdPartyAPI/CheckCustomerNIN?nin='.$nin)
                ->throw()
                ->json();

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
        $response = Http::timeout((int) config('boi_integrations.boi_thirdparty.http_timeout', 120))
            ->withHeaders(['Content-Type' => 'application/json', 'accept' => '*/*'])
            ->post($cacBase.'/Verification/verify-business/'.$rcNumber)
            ->throw()
            ->json();

        if (($response['status'] ?? null) !== 'found' && empty($response['name']) && empty($response['registrationNumber'])) {
            throw new \Exception('Invalid RC number - no company found');
        }

        return $response;
    }
}
