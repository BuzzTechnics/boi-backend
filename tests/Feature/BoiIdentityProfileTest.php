<?php

use Boi\Backend\Services\BOI;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Cache::flush();

    config()->set('boi_integrations.boi_thirdparty.cac_verify_base_url', 'https://cac.example.test:8280');
    config()->set('boi_integrations.boi_thirdparty.username_prod', 'GLOW');
    config()->set('boi_integrations.boi_thirdparty.password_prod', 'glow-pw');
    config()->set('boi_integrations.boi_thirdparty.http_timeout', 5);
    config()->set('boi_integrations.boi_thirdparty.identity_profiles', [
        'adf' => ['username' => 'ADF', 'password' => 'adf-pw'],
    ]);
});

// JWT-shaped fake token so BOI::isValidToken accepts it from the cache on subsequent calls.
function profileJwt(string $tag = 'a'): string
{
    return 'eyJ.eyJhdWQ.'.$tag.str_repeat('a', 16);
}

function callAs(?string $appSlug): void
{
    if ($appSlug === null) {
        request()->headers->remove('X-Boi-App');
    } else {
        request()->headers->set('X-Boi-App', $appSlug);
    }
}

it('authenticates a mapped caller (adf) with that fund\'s own gateway account', function () {
    callAs('adf');

    Http::fake([
        'cac.example.test:8280/Authentication/Authenticate' => Http::response(['token' => profileJwt(), 'success' => true], 200),
        'cac.example.test:8280/Verification/verify-business/RC1' => Http::response(['status' => 'found', 'name' => 'ADF CO LTD', 'registrationNumber' => 'RC1'], 200),
    ]);

    BOI::businessCac('RC1');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/Authentication/Authenticate')
        && ($request->data()['username'] ?? null) === 'ADF');
});

it('audit-logs which gateway account authenticated (account name, never the password)', function () {
    Log::spy();
    callAs('adf');

    Http::fake([
        'cac.example.test:8280/Authentication/Authenticate' => Http::response(['token' => profileJwt(), 'success' => true], 200),
        'cac.example.test:8280/Verification/verify-business/RC1' => Http::response(['status' => 'found', 'name' => 'ADF CO LTD', 'registrationNumber' => 'RC1'], 200),
    ]);

    BOI::businessCac('RC1');

    Log::shouldHaveReceived('info')->withArgs(fn ($message, $context = []) => $message === 'BOI identity gateway authentication'
        && ($context['app'] ?? null) === 'adf'
        && ($context['account'] ?? null) === 'ADF'
        && ($context['per_fund_profile'] ?? null) === true
        && ! array_key_exists('password', $context))->once();
});

it('falls back to the default account for unmapped callers', function () {
    callAs('glow');

    Http::fake([
        'cac.example.test:8280/Authentication/Authenticate' => Http::response(['token' => profileJwt(), 'success' => true], 200),
        'cac.example.test:8280/Verification/verify-business/RC1' => Http::response(['status' => 'found', 'name' => 'GLOW CO LTD', 'registrationNumber' => 'RC1'], 200),
    ]);

    BOI::businessCac('RC1');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/Authentication/Authenticate')
        && ($request->data()['username'] ?? null) === 'GLOW');
});

it('falls back to the default account when a mapped profile has blank credentials', function () {
    config()->set('boi_integrations.boi_thirdparty.identity_profiles', [
        'adf' => ['username' => 'ADF', 'password' => ''],
    ]);
    callAs('adf');

    Http::fake([
        'cac.example.test:8280/Authentication/Authenticate' => Http::response(['token' => profileJwt(), 'success' => true], 200),
        'cac.example.test:8280/Verification/verify-business/RC1' => Http::response(['status' => 'found', 'name' => 'X LTD', 'registrationNumber' => 'RC1'], 200),
    ]);

    BOI::businessCac('RC1');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/Authentication/Authenticate')
        && ($request->data()['username'] ?? null) === 'GLOW');
});

it('caches each profile token under its own key so funds never share a token', function () {
    $seen = [];
    Http::fake([
        'cac.example.test:8280/Authentication/Authenticate' => function ($request) use (&$seen) {
            $seen[] = $request->data()['username'] ?? null;

            return Http::response(['token' => profileJwt(count($seen) === 1 ? 'a' : 'b'), 'success' => true], 200);
        },
        'cac.example.test:8280/Verification/verify-business/*' => Http::response(['status' => 'found', 'name' => 'CO', 'registrationNumber' => 'RC1'], 200),
    ]);

    callAs('adf');
    BOI::businessCac('RC1'); // mints ADF token
    callAs('glow');
    BOI::businessCac('RC2'); // must NOT reuse ADF's cached token -> mints default token
    callAs('adf');
    BOI::businessCac('RC3'); // reuses cached ADF token, no new auth

    expect($seen)->toBe(['ADF', 'GLOW']);
});

it('drops only the caller\'s own stale token on a 401 and re-auths as that fund', function () {
    callAs('adf');

    $authUsers = [];
    Http::fake([
        'cac.example.test:8280/Authentication/Authenticate' => function ($request) use (&$authUsers) {
            $authUsers[] = $request->data()['username'] ?? null;

            return Http::response(['token' => profileJwt('t'.count($authUsers)), 'success' => true], 200);
        },
        'cac.example.test:8280/Verification/verify-business/RC42' => Http::sequence()
            ->push('', 401, ['WWW-Authenticate' => 'Bearer'])
            ->push(['status' => 'found', 'name' => 'RECOVERED LTD', 'registrationNumber' => 'RC42'], 200),
    ]);

    $result = BOI::businessCac('RC42');

    expect($result['name'])->toBe('RECOVERED LTD');
    expect($authUsers)->toBe(['ADF', 'ADF']); // both auths as ADF; the 401 cleared ADF's key, not the default
});
