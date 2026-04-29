<?php

use Boi\Backend\Services\BOI;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    config()->set('boi_integrations.boi_thirdparty.cac_verify_base_url', 'https://cac.example.test:8280');
    config()->set('boi_integrations.boi_thirdparty.api_base_url', 'https://rubikon.example.test:8260');
    config()->set('boi_integrations.boi_thirdparty.username_prod', 'AO');
    config()->set('boi_integrations.boi_thirdparty.password_prod', 'pw');
    config()->set('boi_integrations.boi_thirdparty.http_timeout', 5);
});

// JWT-shaped fake token so BOI::isValidToken accepts it from the cache on subsequent calls.
function fakeJwt(string $tag = 'a'): string
{
    return 'eyJ.eyJhdWQ.'.$tag.str_repeat('a', 16);
}

it('authenticates against the CAC gateway with username (not emailOrUserName)', function () {
    Http::fake([
        'cac.example.test:8280/Authentication/Authenticate' => Http::response([
            'username' => 'AO', 'token' => fakeJwt(), 'success' => true,
        ], 200),
        'cac.example.test:8280/Verification/verify-business/RC823088' => Http::response([
            'status' => 'found',
            'name' => 'CHICKEN REPUBLIC LIMITED',
            'registrationNumber' => 'RC823088',
        ], 200),
    ]);

    $result = BOI::businessCac('RC823088');

    expect($result['name'])->toBe('CHICKEN REPUBLIC LIMITED');

    Http::assertSent(function ($request) {
        if (! str_ends_with($request->url(), '/Authentication/Authenticate')) {
            return false;
        }
        $body = $request->data();

        // Doc the contract: CAC service uses `username`, NOT the Rubikon-style `emailOrUserName`.
        return ($body['username'] ?? null) === 'AO'
            && ! array_key_exists('emailOrUserName', $body);
    });
});

it('sends Authorization: Bearer header to verify-business', function () {
    Http::fake([
        'cac.example.test:8280/Authentication/Authenticate' => Http::response([
            'token' => fakeJwt('x'), 'success' => true,
        ], 200),
        'cac.example.test:8280/Verification/verify-business/RC1' => Http::response([
            'status' => 'found', 'name' => 'OK CO LTD', 'registrationNumber' => 'RC1',
        ], 200),
    ]);

    BOI::businessCac('RC1');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/Verification/verify-business/RC1')) {
            return false;
        }

        return str_starts_with($request->header('Authorization')[0] ?? '', 'Bearer ');
    });
});

it('rejects "not_found" responses where the CAC echoes the input RC back as registrationNumber', function () {
    Http::fake([
        'cac.example.test:8280/Authentication/Authenticate' => Http::response(['token' => fakeJwt(), 'success' => true], 200),
        'cac.example.test:8280/Verification/verify-business/RC000000' => Http::response([
            'status' => 'not_found',
            'name' => null,
            'registrationNumber' => 'RC000000', // echoed back even though the company doesn't exist
        ], 200),
    ]);

    expect(fn () => BOI::businessCac('RC000000'))
        ->toThrow(\Exception::class, 'Invalid RC number - no company found');
});

it('drops a stale CAC token on 401 and retries with a fresh one', function () {
    $authCalls = 0;
    Http::fake([
        'cac.example.test:8280/Authentication/Authenticate' => function () use (&$authCalls) {
            $authCalls++;

            return Http::response(['token' => fakeJwt('t'.$authCalls), 'success' => true], 200);
        },
        'cac.example.test:8280/Verification/verify-business/RC42' => Http::sequence()
            ->push('', 401, ['WWW-Authenticate' => 'Bearer'])
            ->push([
                'status' => 'found', 'name' => 'RECOVERED LTD', 'registrationNumber' => 'RC42',
            ], 200),
    ]);

    $result = BOI::businessCac('RC42');

    expect($result['name'])->toBe('RECOVERED LTD');
    // First auth on cold start, second auth after the 401 invalidated the cached token.
    expect($authCalls)->toBe(2);
});

it('caches the CAC token across calls', function () {
    $authCalls = 0;
    Http::fake([
        'cac.example.test:8280/Authentication/Authenticate' => function () use (&$authCalls) {
            $authCalls++;

            return Http::response(['token' => fakeJwt(), 'success' => true], 200);
        },
        'cac.example.test:8280/Verification/verify-business/*' => Http::response([
            'status' => 'found', 'name' => 'X LTD', 'registrationNumber' => 'RC1',
        ], 200),
    ]);

    BOI::businessCac('RC1');
    BOI::businessCac('RC1');
    BOI::businessCac('RC1');

    expect($authCalls)->toBe(1);
});

it('surfaces CAC auth failure when the gateway returns no token', function () {
    Http::fake([
        'cac.example.test:8280/Authentication/Authenticate' => Http::response([
            'token' => null, 'success' => false, 'message' => 'Invalid Username or Password',
        ], 200),
    ]);

    expect(fn () => BOI::businessCac('RC1'))
        ->toThrow(\Exception::class, 'CAC authentication failed: Invalid Username or Password');
});
