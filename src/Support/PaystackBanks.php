<?php

namespace Boi\Backend\Support;

use Boi\Backend\Support\Concerns\SyncsBankList;
use Illuminate\Support\Facades\Http;

/**
 * Syncs Nigerian banks from Paystack into an Eloquent model (name, code, short_name).
 * Used by consuming apps' seeders and by boi-api.
 */
class PaystackBanks
{
    use SyncsBankList;

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $bankModel
     * @param  callable(string):void|null  $onError  e.g. fn (string $m) => $this->command->error($m)
     */
    public static function sync(string $bankModel, ?callable $onError = null): bool
    {
        if (! class_exists($bankModel)) {
            $onError !== null && $onError("Bank model class does not exist: {$bankModel}");

            return false;
        }

        $response = Http::get('https://api.paystack.co/bank');

        if (! $response->successful()) {
            $onError !== null && $onError('Failed to fetch banks from Paystack API: '.$response->status());

            return false;
        }

        $json = $response->json();

        if (! isset($json['data']) || ! is_array($json['data'])) {
            $onError !== null && $onError('Invalid Paystack response: expected "data" array.');

            return false;
        }

        $banks = [];
        foreach ($json['data'] as $bank) {
            $code = $bank['code'] ?? null;
            $name = $bank['name'] ?? null;
            if ($code === null || $name === null) {
                continue;
            }
            $banks[] = ['code' => (string) $code, 'name' => (string) $name];
        }

        self::upsertBanks($bankModel, $banks, (bool) config('banks.prune', true));

        return true;
    }
}
