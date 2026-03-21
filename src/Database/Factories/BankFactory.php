<?php

namespace Boi\Backend\Database\Factories;

use Boi\Backend\Models\Bank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bank>
 */
class BankFactory extends Factory
{
    protected $model = Bank::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company().' Bank',
            'code' => $this->faker->unique()->numerify('###'),
        ];
    }
}
