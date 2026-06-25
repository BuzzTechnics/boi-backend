<?php

namespace Boi\Backend\Support;

use Illuminate\Support\Arr;

/**
 * Reusable helpers for the Mono GSI / Global-Standing (direct-debit) mandate webhook.
 *
 * Mono signs each webhook with a `mono-webhook-secret` header that must match the
 * secret configured on the receiving app, and posts a body shaped like:
 *   { event, event_id, data, timestamp }
 * where `data` may carry the mandate id/status under a few different keys depending
 * on the event object.
 *
 * Consumer apps wire this into their own WebhooksController so the verify + parse
 * logic is not reimplemented per app:
 *
 *   if (! MonoWebhook::verify($request->header('mono-webhook-secret'), config('services.mono.webhook_secret'))) {
 *       abort(401);
 *   }
 *   ['event' => $event, 'mandateId' => $id, 'status' => $status, 'readyToDebit' => $ready]
 *       = MonoWebhook::parse($request->all());
 */
final class MonoWebhook
{
    /**
     * Status values (lowercased) that mean the customer has authorized the mandate
     * and it is ready to debit.
     */
    public const READY_STATUSES = ['approved', 'active', 'ready', 'ready_to_debit', 'successful', 'completed'];

    /**
     * Constant-time comparison of the incoming `mono-webhook-secret` header against
     * the app's configured secret. Returns false (never throws) when either side is
     * missing/blank so a misconfigured app fails closed.
     */
    public static function verify(?string $headerSecret, ?string $expectedSecret): bool
    {
        $expected = (string) $expectedSecret;
        $provided = (string) $headerSecret;

        if ($expected === '' || $provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    /**
     * Normalize a Mono webhook body into a flat, predictable shape.
     *
     * @param  array<string, mixed>  $payload  The raw webhook body ({ event, event_id, data, timestamp }).
     * @return array{event: string, mandateId: ?string, status: ?string, readyToDebit: bool}
     */
    public static function parse(array $payload): array
    {
        $event = (string) ($payload['event'] ?? '');
        $data = (array) ($payload['data'] ?? []);

        // The mandate id sits under one of a few keys depending on the event object.
        $mandateId = $data['id']
            ?? $data['mandate']
            ?? $data['mandate_id']
            ?? Arr::get($data, 'object.id')
            ?? Arr::get($data, 'mandate.id');

        $status = $data['status'] ?? Arr::get($data, 'object.status');

        $readyToDebit = (bool) ($data['ready_to_debit'] ?? Arr::get($data, 'object.ready_to_debit', false));

        // events.mandates.ready (ready_to_debit=true) or an approved/active status
        // means the customer has authorized the GSI mandate.
        $readyToDebit = $readyToDebit
            || in_array(strtolower((string) $status), self::READY_STATUSES, true);

        return [
            'event' => $event,
            'mandateId' => $mandateId !== null ? (string) $mandateId : null,
            'status' => $status !== null ? (string) $status : null,
            'readyToDebit' => $readyToDebit,
        ];
    }
}
