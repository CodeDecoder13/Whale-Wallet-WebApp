<?php

namespace App\Actions;

use App\Models\Card;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class UpdateTransaction
{
    /** Edits an expense: puts the old amount back on the old card and charges the new one. $data is validated input. */
    public function handle(Transaction $t, array $data): Transaction
    {
        return DB::transaction(function () use ($t, $data) {
            // lock in id order so two concurrent edits cannot deadlock
            $cards = Card::lockForUpdate()->whereIn('id', [$t->card_id, $data['card_id']])->orderBy('id')->get()->keyBy('id');
            $cards[$t->card_id]->increment('balance', $t->amount);
            $cards[$data['card_id']]->decrement('balance', $data['amount']);

            $t->update($data);

            return $t;
        });
    }
}
