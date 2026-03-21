<?php

namespace Boi\Backend\Database\Seeders;

use Boi\Backend\Models\BankStatement;
use Illuminate\Database\Seeder;

/**
 * Seeds sample bank statements for an existing application row (boi-api DB).
 *
 * Set BANK_STATEMENT_SEED_APPLICATION_ID in .env or the seeder will no-op.
 */
class BankStatementSeeder extends Seeder
{
    public function run(): void
    {
        $applicationId = (int) env('BANK_STATEMENT_SEED_APPLICATION_ID', 0);

        if ($applicationId < 1) {
            return;
        }

        BankStatement::factory()
            ->count(2)
            ->create(['application_id' => $applicationId]);
    }
}
