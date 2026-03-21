<?php

namespace Boi\Backend\Database\Seeders;

use Boi\Backend\Support\PaystackBanks;
use Illuminate\Database\Seeder;

/**
 * Optional seeder for apps that still use the package without boi-api.
 * Prefer calling PaystackBanks::sync() from your own DatabaseSeeder with your Bank model.
 */
class BanksSeeder extends Seeder
{
    public function run(): void
    {
        $model = config('banks.model', 'App\\Models\\Bank');
        PaystackBanks::sync($model, fn (string $m) => $this->command?->error($m));
    }
}
