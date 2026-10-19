<?php

namespace App\Actions;

use App\Enums\TransactionSource;
use App\Models\Category;
use App\Models\Receipt;
use App\Models\RecurringItem;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PayRecurring
{
    public function __construct(private CreateTransaction $create) {}

    /** Records a payment as an expense on the card, then moves the item to its next due date (a loan loses one payment). */
    public function handle(RecurringItem $item, int $cardId, int $amount, string $paidOn, TransactionSource $source = TransactionSource::Manual, ?string $reference = null, ?int $receiptId = null): Transaction
    {
        return DB::transaction(function () use ($item, $cardId, $amount, $paidOn, $source, $reference, $receiptId) {
            $item = RecurringItem::lockForUpdate()->findOrFail($item->id);
            abort_if($item->installments === 0, 422, 'This loan is already paid off.');
            abort_if($item->cancelled_at, 422, 'This subscription is cancelled. Renew it first.');

            // proof of payment: a scanned receipt the user checked becomes this payment's receipt
            $receipt = $receiptId ? Receipt::lockForUpdate()->findOrFail($receiptId) : null;
            abort_if($receipt && ($receipt->status !== 'parsed' || $receipt->purpose !== 'expense'), 422, 'This receipt was already handled.');

            $transaction = $this->create->handle([
                'merchant' => $item->name, 'amount' => $amount, 'card_id' => $cardId, 'occurred_at' => $paidOn,
                'category_id' => Category::where('name', 'Bills')->value('id') ?? $item->category_id,
                'source' => $receipt ? TransactionSource::Receipt : $source, 'recurring_item_id' => $item->id,
                'reference' => $reference, 'receipt_id' => $receipt?->id,
            ]);
            $item->advanceAfterPayment();

            if ($receipt) {
                $receipt->update(['status' => 'confirmed']);

                if (! auth()->user()->keep_receipts && $receipt->path) {
                    Storage::disk()->delete($receipt->path);
                    $receipt->update(['path' => null]);
                }
            }

            return $transaction;
        });
    }
}
