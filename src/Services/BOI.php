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
     *
     * BOI provisions one gateway account per consuming application, so the
     * credentials are resolved per-caller: a fund mapped in
     * boi_integrations.boi_thirdparty.identity_profiles (keyed by the X-Boi-App
     * slug) authenticates as its own account and each profile's token is cached
     * under its own key so profiles never clobber each other. Unmapped callers
     * use the default account (username_prod/password_prod), unchanged.
     */
    public static function getCacToken(): string
    {
        [$cacheKey, $username, $password] = self::resolveCacProfile();

        $cachedToken = Cache::get($cacheKey);

        if ($cachedToken !== null && self::isValidToken($cachedToken)) {
            return $cachedToken;
        }

        $base = config('boi_integrations.boi_thirdparty.cac_verify_base_url');

        // Audit which BOI gateway account each authentication uses (never the
        // password). Fires only on a cache miss, i.e. once per token lifetime
        // per fund — enough to confirm e.g. an ADF caller authenticates as ADF.
        \Illuminate\Support\Facades\Log::info('BOI identity gateway authentication', [
            'app' => self::callerAppSlug(),
            'account' => $username,
            'per_fund_profile' => $cacheKey !== self::CAC_CACHE_KEY,
        ]);

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
        Cache::put($cacheKey, $token, now()->addHours(max(1, $hours)));

        return $token;
    }

    /**
     * Pick the IdentityVerification.API gateway credentials for the current
     * caller and the cache key its token lives under. A caller whose X-Boi-App
     * slug is mapped in `identity_profiles` with a non-empty username AND
     * password gets its own account (cache key suffixed with the slug);
     * everyone else gets the default account under the shared CAC cache key —
     * identical to the pre-profiles behaviour.
     *
     * @return array{0: string, 1: ?string, 2: ?string} [cacheKey, username, password]
     */
    private static function resolveCacProfile(): array
    {
        $defaultUsername = config('boi_integrations.boi_thirdparty.username_prod')
            ?? config('boi_integrations.boi_thirdparty.username');
        $defaultPassword = config('boi_integrations.boi_thirdparty.password_prod')
            ?? config('boi_integrations.boi_thirdparty.password');

        $slug = self::callerAppSlug();
        if ($slug !== null) {
            $profile = config('boi_integrations.boi_thirdparty.identity_profiles.'.$slug);
            $username = is_array($profile) ? ($profile['username'] ?? null) : null;
            $password = is_array($profile) ? ($profile['password'] ?? null) : null;
            if (is_string($username) && $username !== '' && is_string($password) && $password !== '') {
                return [self::CAC_CACHE_KEY.':'.$slug, $username, $password];
            }
        }

        return [self::CAC_CACHE_KEY, $defaultUsername, $defaultPassword];
    }

    /**
     * Cache key the current caller's CAC/identity gateway token lives under, so
     * a 401 retry invalidates the right profile's token (not just the default).
     */
    private static function currentCacCacheKey(): string
    {
        return self::resolveCacProfile()[0];
    }

    /**
     * The originating fund's app slug, taken from the X-Boi-App header the proxy
     * / integrations client forwards. Mirrors BvnNinCallLogger::projectName so
     * credential selection and call attribution key off the same signal. The
     * package default 'app' is treated as unset. Null for direct calls / jobs.
     */
    private static function callerAppSlug(): ?string
    {
        if (! function_exists('request')) {
            return null;
        }

        $appHeader = (string) config('boi_proxy.app_header', 'X-Boi-App');
        $caller = request()?->header($appHeader);

        if (is_string($caller) && $caller !== '' && strtolower($caller) !== 'app') {
            return strtolower($caller);
        }

        return null;
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
        return self::identityDetails('bvn', $bvn);
    }

    /**
     * @return array<string, mixed>
     */
    public static function customerNin(string $nin): array
    {
        return self::identityDetails('nin', $nin);
    }

    /**
     * BVN/NIN lookups live on the IdentityVerification.API gateway (the
     * cac_verify_base_url host) since 2026-08-20: BOI removed
     * /api/ThirdPartyAPI/CheckCustomer{BVN,NIN} from the :8249 service, and the
     * identity gateway's GET /Verification/get-{bvn,nin}-details/{number} is the
     * lookup replacement (same auth + credentials as CAC verification; each
     * consuming application is provisioned its own user, e.g. GLOW).
     *
     * A miss is HTTP 404 ("BVN Details not found"), which throw() surfaces as a
     * RequestException — the same failure path callers already handle. Hits are
     * normalised to the legacy :8249 shape (`phone`, `home_address`,
     * message='Successful') so downstream consumers are unchanged.
     *
     * @param  'bvn'|'nin'  $kind
     * @return array<string, mixed>
     */
    private static function identityDetails(string $kind, string $number): array
    {
        $base = config('boi_integrations.boi_thirdparty.cac_verify_base_url');
        $url = $base.'/Verification/get-'.$kind.'-details/'.$number;
        $timeout = (int) config('boi_integrations.boi_thirdparty.http_timeout', 120);

        $call = static fn () => Http::timeout($timeout)
            ->connectTimeout(10)
            ->withHeaders([
                'Authorization' => 'Bearer '.self::getCacToken(),
                'accept' => '*/*',
            ])
            ->get($url);

        $record = static fn () => BvnNinCallLogger::record(
            kind: $kind,
            identifier: $number,
            endpoint: $url,
            method: 'GET',
            payload: null,
            call: $call,
        );

        $response = $record();

        if ($response->status() === 401) {
            // Stale cached gateway token: mint a fresh one and retry once.
            Cache::forget(self::currentCacCacheKey());
            $response = $record();
        }

        $body = $response->throw()->json();

        if (! is_array($body)) {
            return [];
        }

        $body['phone'] ??= $body['mobile'] ?? null;
        $body['home_address'] ??= $body['addressLine'] ?? null;
        $body['message'] = 'Successful';

        return $body;
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
                Cache::forget(self::currentCacCacheKey());

                return $call();
            }

            throw $e;
        }
    }
}
