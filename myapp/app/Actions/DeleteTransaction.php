<?php

namespace App\Actions;

use App\Enums\TransactionType;
use App\Models\Card;
use App\Models\Pod;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class DeleteTransaction
{
    /** Removes the row and undoes its effect on card and pod balances. */
    public function handle(Transaction $t): void
    {
        DB::transaction(function () use ($t) {
            if ($t->type === TransactionType::Expense) {
                $this->move(Card::class, $t->card_id, 'balance', $t->amount);
            } elseif ($t->type === TransactionType::Transfer) {
                // card side gives back what it sent (or takes back what it received); same for the other side
                $fromPod = $t->from_pod_id !== null;
                $this->move(Card::class, $t->card_id, 'balance', $fromPod ? -$t->amount : $t->amount);
                $this->move(Card::class, $t->to_card_id, 'balance', -$t->amount);
                $this->move(Pod::class, $t->to_pod_id, 'saved_amount', -$t->amount);
                $this->move(Pod::class, $t->from_pod_id, 'saved_amount', $t->amount);
            }

            $t->delete();
        });
    }

    /** Skips a missing side (its card or pod was deleted since). */
    private function move(string $model, ?int $id, string $field, int $delta): void
    {
        $model::lockForUpdate()->find($id)?->increment($field, $delta);
    }
}
