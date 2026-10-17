<?php

namespace App\Actions;

use App\Enums\TransactionSource;
use App\Models\Category;
use App\Models\RecurringItem;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class PayRecurring
{
    public function __construct(private CreateTransaction $create) {}

    /** Records a payment as an expense on the card, then moves the item to its next due date (a loan loses one payment). */
    public function handle(RecurringItem $item, int $cardId, int $amount, string $paidOn, TransactionSource $source = TransactionSource::Manual): Transaction
    {
        return DB::transaction(function () use ($item, $cardId, $amount, $paidOn, $source) {
            $item = RecurringItem::lockForUpdate()->findOrFail($item->id);
            abort_if($item->installments === 0, 422, 'This loan is already paid off.');

            $transaction = $this->create->handle([
                'merchant' => $item->name, 'amount' => $amount, 'card_id' => $cardId, 'occurred_at' => $paidOn,
                'category_id' => Category::where('name', 'Bills')->value('id') ?? $item->category_id,
                'source' => $source, 'recurring_item_id' => $item->id,
            ]);
            $item->advanceAfterPayment();

            return $transaction;
        });
    }
}
