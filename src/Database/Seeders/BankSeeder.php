<?php

namespace Boi\Backend\Database\Seeders;

use Boi\Backend\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        if (Bank::query()->exists()) {
            return;
        }

        Bank::factory()->count(10)->create();
    }
}
