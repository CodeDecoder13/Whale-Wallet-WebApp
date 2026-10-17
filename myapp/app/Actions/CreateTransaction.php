<?php

namespace App\Actions;

use App\Enums\TransactionType;
use App\Models\Card;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class CreateTransaction
{
    /** Logs an expense and charges it to its card. $data is validated input (amount in centavos). */
    public function handle(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            Card::lockForUpdate()->findOrFail($data['card_id'])->decrement('balance', $data['amount']);

            return Transaction::create([...$data, 'type' => TransactionType::Expense]);
        });
    }
}
