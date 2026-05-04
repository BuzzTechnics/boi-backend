<?php

use Boi\Backend\Models\EdocCall;
use Boi\Backend\Services\EdocCallLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('database.default', 'testing');
    config()->set('database.connections.testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    config()->set('boi_backend.project', 'tests');
});

it('persists a successful call with timing, status, and scrubbed payload', function () {
    Http::fake([
        'edoc.example.com/v1/external/master/banks' => Http::response(['banks' => [['id' => 1]]], 200),
    ]);

    $response = EdocCallLogger::record(
        endpoint: 'https://edoc.example.com/v1/external/master/banks',
        method: 'GET',
        payload: ['email' => 'jane@example.com', 'bvn' => '12345678901', 'token' => 'super-secret'],
        call: fn () => Http::get('https://edoc.example.com/v1/external/master/banks'),
    );

    expect($response->status())->toBe(200);

    $row = EdocCall::query()->latest('id')->first();
    expect($row)->not->toBeNull();
    expect($row->project)->toBe('tests');
    expect($row->method)->toBe('GET');
    expect($row->endpoint)->toBe('https://edoc.example.com/v1/external/master/banks');
    expect($row->response_status)->toBe(200);
    expect($row->succeeded)->toBeTrue();
    expect($row->duration_ms)->toBeGreaterThanOrEqual(0);
    expect($row->request_payload['bvn'])->toBe('[REDACTED]');
    expect($row->request_payload['token'])->toBe('[REDACTED]');
    expect($row->request_payload['email'])->toBe('jane@example.com');
});

it('persists a failed call when the gateway returns a non-2xx status', function () {
    Http::fake([
        '*' => Http::response(['message' => 'gone'], 410),
    ]);

    $response = EdocCallLogger::record(
        endpoint: 'https://edoc.example.com/foo',
        method: 'POST',
        payload: ['x' => 1],
        call: fn () => Http::post('https://edoc.example.com/foo', ['x' => 1]),
    );

    expect($response->status())->toBe(410);
    $row = EdocCall::query()->latest('id')->first();
    expect($row->succeeded)->toBeFalse();
    expect($row->response_status)->toBe(410);
});

it('persists a row and re-throws when the callback throws', function () {
    expect(fn () => EdocCallLogger::record(
        endpoint: 'https://edoc.example.com/explode',
        method: 'POST',
        payload: null,
        call: function () {
            throw new \RuntimeException('boom');
        },
    ))->toThrow(\RuntimeException::class, 'boom');

    $row = EdocCall::query()->latest('id')->first();
    expect($row)->not->toBeNull();
    expect($row->succeeded)->toBeFalse();
    expect($row->exception_class)->toBe(\RuntimeException::class);
    expect($row->response_body)->toMatchArray(['error' => 'boom']);
});
