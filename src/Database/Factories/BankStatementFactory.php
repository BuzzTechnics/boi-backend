<?php

namespace Boi\Backend\Database\Factories;

use Boi\Backend\Models\BankStatement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankStatement>
 */
class BankStatementFactory extends Factory
{
    protected $model = BankStatement::class;

    public function definition(): array
    {
        return [
            'application_id' => 1,
            'app' => config('boi_proxy.app', 'glow'),
            'bank' => $this->faker->company(),
            'account_number' => $this->faker->numerify('##########'),
            'account_name' => $this->faker->name(),
            'account_type' => $this->faker->randomElement(['savings', 'current']),
            'bvn' => $this->faker->optional()->numerify('###########'),
            'email' => $this->faker->optional()->safeEmail(),
            'bank_statement' => null,
            'csv_url' => null,
            'consent_id' => null,
            'edoc_status' => 'pending',
            'statement_generated' => false,
        ];
    }
}
