<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // card belongs to the same user as the transaction
            'card_id' => fn (array $a) => Card::factory()->state(['user_id' => $a['user_id']]),
            'type' => TransactionType::Expense,
            'amount' => fake()->numberBetween(1_000, 200_000),
            'merchant' => fake()->company(),
            'occurred_at' => now(),
        ];
    }
}
