<?php

use Boi\Backend\Support\Banks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SyncTestBank extends Model
{
    protected $table = 'banks';

    protected $guarded = [];
}

beforeEach(function () {
    config()->set('database.default', 'bank_testing');
    config()->set('database.connections.bank_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    Schema::create('banks', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('code');
        $t->string('short_name')->nullable();
        $t->timestamps();
    });
});

it('syncs from Monnify when provider is monnify', function () {
    config()->set('banks.provider', 'monnify');
    config()->set('banks.monnify', [
        'base_url' => 'https://api.monnify.com',
        'api_key' => 'MK_TEST',
        'secret_key' => 'SK_TEST',
    ]);

    Http::fake([
        'api.monnify.com/api/v1/auth/login' => Http::response([
            'requestSuccessful' => true,
            'responseMessage' => 'success',
            'responseBody' => ['accessToken' => 'tok-123', 'expiresIn' => 3599],
        ], 200),
        'api.monnify.com/api/v1/banks' => Http::response([
            'requestSuccessful' => true,
            'responseBody' => [
                ['name' => 'Zenith bank', 'code' => '057', 'nipBankCode' => '000015'],
                ['name' => 'BANKLY MFB', 'code' => '090529'],
            ],
        ], 200),
    ]);

    $ok = Banks::sync(SyncTestBank::class);

    expect($ok)->toBeTrue();
    expect(SyncTestBank::count())->toBe(2);
    // curated short name for a shared commercial code
    expect(SyncTestBank::where('code', '057')->value('short_name'))->toBe('Zenith');
    // Monnify-specific MFB code is stored verbatim
    expect(SyncTestBank::where('code', '090529')->value('name'))->toBe('BANKLY MFB');
});

it('syncs from Paystack when provider is paystack (default)', function () {
    config()->set('banks.provider', 'paystack');

    Http::fake([
        'api.paystack.co/bank' => Http::response([
            'status' => true,
            'data' => [
                ['name' => 'Zenith Bank', 'code' => '057'],
                ['name' => 'Bankly MFB', 'code' => '51341'],
            ],
        ], 200),
    ]);

    $ok = Banks::sync(SyncTestBank::class);

    expect($ok)->toBeTrue();
    expect(SyncTestBank::count())->toBe(2);
    expect(SyncTestBank::where('code', '057')->value('short_name'))->toBe('Zenith');
    // Paystack's own MFB code
    expect(SyncTestBank::where('code', '51341')->value('name'))->toBe('Bankly MFB');
});

it('prunes codes the provider no longer returns (mirror sync)', function () {
    config()->set('banks.provider', 'paystack');
    config()->set('banks.prune', true);

    // a stale row from a previous/other provider
    SyncTestBank::create(['name' => 'Old MFB', 'code' => 'STALE999', 'short_name' => 'Old']);

    Http::fake([
        'api.paystack.co/bank' => Http::response([
            'status' => true,
            'data' => [['name' => 'Zenith Bank', 'code' => '057']],
        ], 200),
    ]);

    Banks::sync(SyncTestBank::class);

    expect(SyncTestBank::where('code', 'STALE999')->exists())->toBeFalse();
    expect(SyncTestBank::where('code', '057')->exists())->toBeTrue();
    expect(SyncTestBank::count())->toBe(1);
});

it('keeps stale rows when prune is disabled', function () {
    config()->set('banks.provider', 'paystack');
    config()->set('banks.prune', false);

    SyncTestBank::create(['name' => 'Old MFB', 'code' => 'STALE999', 'short_name' => 'Old']);

    Http::fake([
        'api.paystack.co/bank' => Http::response([
            'status' => true,
            'data' => [['name' => 'Zenith Bank', 'code' => '057']],
        ], 200),
    ]);

    Banks::sync(SyncTestBank::class);

    expect(SyncTestBank::where('code', 'STALE999')->exists())->toBeTrue();
    expect(SyncTestBank::count())->toBe(2);
});

it('fails cleanly when Monnify credentials are missing', function () {
    config()->set('banks.provider', 'monnify');
    config()->set('banks.monnify', ['base_url' => 'https://api.monnify.com', 'api_key' => null, 'secret_key' => null]);

    $error = null;
    $ok = Banks::sync(SyncTestBank::class, function (string $m) use (&$error) {
        $error = $m;
    });

    expect($ok)->toBeFalse();
    expect($error)->toContain('Monnify credentials');
    expect(SyncTestBank::count())->toBe(0);
});
