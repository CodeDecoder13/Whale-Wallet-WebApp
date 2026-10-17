<?php

namespace Database\Factories;

use App\Enums\RecurringKind;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->word(),
            'amount' => fake()->numberBetween(10_000, 500_000),
            'kind' => RecurringKind::Bill,
        ];
    }
}
