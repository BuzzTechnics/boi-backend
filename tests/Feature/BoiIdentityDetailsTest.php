<?php

use Boi\Backend\Services\BOI;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * BVN/NIN lookups moved to the identity gateway (2026-08-20): BOI removed
 * /api/ThirdPartyAPI/CheckCustomer{BVN,NIN} from the :8249 service; the
 * replacement is GET /Verification/get-{bvn,nin}-details/{number} on the
 * cac_verify_base_url host, sharing the CAC auth.
 */
function identityJwt(string $tag = 'a'): string
{
    return 'eyJ.eyJhdWQ.'.$tag.str_repeat('a', 16);
}

beforeEach(function () {
    Cache::flush();

    config()->set('boi_integrations.boi_thirdparty.cac_verify_base_url', 'https://cac.example.test:8280');
    config()->set('boi_integrations.boi_thirdparty.username_prod', 'GLOW');
    config()->set('boi_integrations.boi_thirdparty.password_prod', 'pw');
    config()->set('boi_integrations.boi_thirdparty.http_timeout', 5);
});

it('looks up a BVN on the identity gateway and normalises to the legacy shape', function () {
    Http::fake([
        'cac.example.test:8280/Authentication/Authenticate' => Http::response([
            'token' => identityJwt(), 'success' => true,
        ], 200),
        'cac.example.test:8280/Verification/get-bvn-details/22222222222' => Http::response([
            'idNumber' => '22222222222',
            'firstName' => 'ADA',
            'middleName' => null,
            'lastName' => 'OBI',
            'gender' => 'f',
            'dateOfBirth' => '1990-01-02T00:00:00',
            'mobile' => '2348030000000',
            'message' => 'Details retrieved',
            'statusCode' => 200,
        ], 200),
    ]);

    $result = BOI::customerBvn('22222222222');

    // Consumers of the old :8249 response read `phone` and gate on message='Successful'.
    expect($result['firstName'])->toBe('ADA')
        ->and($result['phone'])->toBe('2348030000000')
        ->and($result['message'])->toBe('Successful');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/Verification/get-bvn-details/22222222222')
        && $request->method() === 'GET'
        && str_starts_with((string) $request->header('Authorization')[0], 'Bearer '));
});

it('maps the NIN addressLine to the legacy home_address field', function () {
    Http::fake([
        'cac.example.test:8280/Authentication/Authenticate' => Http::response(['token' => identityJwt(), 'success' => true], 200),
        'cac.example.test:8280/Verification/get-nin-details/33333333333' => Http::response([
            'firstName' => 'BOLA', 'lastName' => 'ADE', 'mobile' => '2348020000000',
            'addressLine' => '12 Marina Rd', 'message' => 'ok', 'statusCode' => 200,
        ], 200),
    ]);

    $result = BOI::customerNin('33333333333');

    expect($result['home_address'])->toBe('12 Marina Rd')
        ->and($result['phone'])->toBe('2348020000000')
        ->and($result['message'])->toBe('Successful');
});

it('throws on a miss (gateway answers 404 Details not found)', function () {
    Http::fake([
        'cac.example.test:8280/Authentication/Authenticate' => Http::response(['token' => identityJwt(), 'success' => true], 200),
        'cac.example.test:8280/Verification/get-bvn-details/*' => Http::response([
            'firstName' => null, 'message' => 'BVN Details not found', 'statusCode' => 404,
        ], 404),
    ]);

    BOI::customerBvn('44444444444');
})->throws(RequestException::class);

it('re-authenticates once when the cached gateway token is rejected', function () {
    Cache::put('boi_backend:boi_cac_verify_token', identityJwt('stale'), now()->addHours(6));

    Http::fake([
        'cac.example.test:8280/Authentication/Authenticate' => Http::response(['token' => identityJwt('fresh'), 'success' => true], 200),
        'cac.example.test:8280/Verification/get-bvn-details/22222222222' => Http::sequence()
            ->push(['message' => 'unauthorized'], 401)
            ->push(['firstName' => 'ADA', 'mobile' => '234803', 'statusCode' => 200], 200),
    ]);

    $result = BOI::customerBvn('22222222222');

    expect($result['firstName'])->toBe('ADA')
        ->and($result['message'])->toBe('Successful');
});
