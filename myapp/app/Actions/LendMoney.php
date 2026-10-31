<?php

namespace App\Actions;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Mail\LendNotice;
use App\Models\Card;
use App\Models\Lend;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/** Sends cash out of a card to a person, keeps the proof, and tells them by email. Money lent is a transfer, never spending. */
class LendMoney
{
    public function __construct(private SettleReceipt $receipts) {}

    /** @param  array{name: string, email: string, card_id: int, amount: int, paid_on: string, due_on: string, remind_before: int, reference?: ?string, receipt_id?: ?int}  $d  amount in centavos */
    public function handle(array $d): Lend
    {
        $lend = DB::transaction(function () use ($d) {
            $receipt = $this->receipts->claim($d['receipt_id'] ?? null);
            $card = Card::lockForUpdate()->findOrFail($d['card_id']);

            if ($card->balance < $d['amount']) {
                throw ValidationException::withMessages(['amount' => __('Not enough balance.')]);
            }

            $card->decrement('balance', $d['amount']);
            // a transfer with no pod or card on the other side: deleting it gives the money back to the card
            Transaction::create([
                'card_id' => $card->id, 'type' => TransactionType::Transfer, 'amount' => $d['amount'],
                'merchant' => 'Lent to '.$d['name'], 'note' => "{$card->name} to {$d['name']}", 'occurred_at' => $d['paid_on'],
                'reference' => $d['reference'] ?? null, 'receipt_id' => $receipt?->id,
                'source' => $receipt ? TransactionSource::Receipt : TransactionSource::Manual,
            ]);
            $this->receipts->finish($receipt);

            return Lend::create([
                'name' => $d['name'], 'email' => $d['email'], 'amount' => $d['amount'], 'card_id' => $card->id,
                'lent_on' => $d['paid_on'], 'due_on' => $d['due_on'], 'remind_before' => $d['remind_before'], 'reference' => $d['reference'] ?? null,
            ]);
        });

        $user = auth()->user();
        dispatch(fn () => Mail::to($lend->email)->send(new LendNotice($lend, $user, 'new')))->afterResponse();

        return $lend;
    }
}
