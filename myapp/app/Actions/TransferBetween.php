<?php

namespace App\Actions;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Card;
use App\Models\Pod;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferBetween
{
    /** $from and $to are "card:ID" or "pod:ID". Recorded as one transfer row, which never counts as spending. */
    public function handle(string $from, string $to, int $amount, ?string $reference = null, ?int $receiptId = null, ?string $on = null): Transaction
    {
        return DB::transaction(function () use ($from, $to, $amount, $reference, $receiptId, $on) {
            $src = $this->find($from);
            $dst = $this->find($to);

            if ($src->{$this->field($src)} < $amount) {
                throw ValidationException::withMessages(['amount' => __('Not enough balance.')]);
            }

            $src->decrement($this->field($src), $amount);
            $dst->increment($this->field($dst), $amount);

            return Transaction::create([
                'card_id' => ($src instanceof Card ? $src : $dst)->id,
                'to_card_id' => $src instanceof Card && $dst instanceof Card ? $dst->id : null,
                'to_pod_id' => $dst instanceof Pod ? $dst->id : null,
                'from_pod_id' => $src instanceof Pod ? $src->id : null,
                'type' => TransactionType::Transfer,
                'amount' => $amount,
                'merchant' => 'Transfer',
                'note' => "{$src->name} to {$dst->name}",
                'occurred_at' => $on ?? now(),
                'reference' => $reference,
                'receipt_id' => $receiptId,
                'source' => $receiptId ? TransactionSource::Receipt : TransactionSource::Manual,
            ]);
        });
    }

    private function find(string $ref): Card|Pod
    {
        [$type, $id] = explode(':', $ref);

        return ($type === 'card' ? Card::class : Pod::class)::lockForUpdate()->findOrFail($id);
    }

    private function field(Card|Pod $m): string
    {
        return $m instanceof Card ? 'balance' : 'saved_amount';
    }
}
