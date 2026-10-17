<?php

namespace Database\Factories;

use App\Enums\CardType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->word(),
            'type' => fake()->randomElement(CardType::cases()),
            'balance' => fake()->numberBetween(0, 1_000_000),
        ];
    }
}
