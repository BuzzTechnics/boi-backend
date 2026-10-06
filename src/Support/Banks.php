<?php

namespace Boi\Backend\Support;

/**
 * Entry point for syncing the bank list. Dispatches to the provider configured
 * in config('banks.provider') (BOI_BANK_PROVIDER): 'monnify' or 'paystack'
 * (default). Consuming apps should call this instead of a specific provider so
 * switching providers is an env change, not a code change.
 */
class Banks
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $bankModel
     * @param  callable(string):void|null  $onError
     */
    public static function sync(string $bankModel, ?callable $onError = null): bool
    {
        return match (config('banks.provider', 'paystack')) {
            'monnify' => MonnifyBanks::sync($bankModel, $onError),
            default => PaystackBanks::sync($bankModel, $onError),
        };
    }

    public static function provider(): string
    {
        return config('banks.provider', 'paystack');
    }
}
