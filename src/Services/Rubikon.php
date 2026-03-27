<?php

namespace Boi\Backend\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Rubikon customer API (registration / customer number lookup).
 */
class Rubikon
{
    private const CACHE_KEY = 'boi_backend:rubikon_api_token';

    /**
     * @return array<string, mixed>
     */
    public static function getByCustomerNumber(string $customerNumber): array
    {
        return self::executeWithRetry(function () use ($customerNumber) {
            $base = config('boi_integrations.rubikon.api_base_url');
            $timeout = (int) config('boi_integrations.rubikon.http_timeout', 120);

            return Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer '.self::getToken(),
                    'Content-Type' => 'application/json',
                    'accept' => '*/*',
                ])
                ->get($base.'/api/Customer/GetByCustomerNumber', [
                    'customerNumber' => $customerNumber,
                ])
                ->throw()
                ->json();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function getByRegistrationNumber(string $registrationNo): array
    {
        return self::executeWithRetry(function () use ($registrationNo) {
            $base = config('boi_integrations.rubikon.api_base_url');
            $timeout = (int) config('boi_integrations.rubikon.http_timeout', 120);

            return Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer '.self::getToken(),
                    'Content-Type' => 'application/json',
                    'accept' => '*/*',
                ])
                ->get($base.'/api/Customer/GetByRegistrationNumber', [
                    'registrationNo' => $registrationNo,
                ])
                ->throw()
                ->json();
        });
    }

    public static function getToken(): string
    {
        $token = Cache::get(self::CACHE_KEY);
        if ($token && preg_match('/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_=]+$/', $token)) {
            return trim((string) $token);
        }

        $base = config('boi_integrations.rubikon.api_base_url');
        $timeout = (int) config('boi_integrations.rubikon.http_timeout', 120);

        $username = config('boi_integrations.rubikon.username_prod')
            ?? config('boi_integrations.rubikon.username')
            ?? config('boi_integrations.boi_thirdparty.username_prod')
            ?? config('boi_integrations.boi_thirdparty.username');
        $password = config('boi_integrations.rubikon.password_prod')
            ?? config('boi_integrations.rubikon.password')
            ?? config('boi_integrations.boi_thirdparty.password_prod')
            ?? config('boi_integrations.boi_thirdparty.password');

        $response = Http::timeout($timeout)
            ->withHeaders(['Content-Type' => 'application/json', 'accept' => '*/*'])
            ->asJson()
            ->post($base.'/api/auth/Authenticate', [
                'username' => $username,
                'password' => $password,
            ])
            ->throw()
            ->json();

        if (empty($response['token'])) {
            throw new \Exception('Authentication failed: No token received');
        }

        $token = trim((string) $response['token']);
        $hours = (int) config('boi_integrations.rubikon.token_cache_ttl_hours', 6);
        Cache::put(self::CACHE_KEY, $token, now()->addHours(max(1, $hours)));

        return $token;
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private static function executeWithRetry(callable $callback): array
    {
        try {
            return $callback();
        } catch (RequestException $e) {
            if ($e->response->status() === 401) {
                Cache::forget(self::CACHE_KEY);

                return $callback();
            }
            throw $e;
        }
    }
}
