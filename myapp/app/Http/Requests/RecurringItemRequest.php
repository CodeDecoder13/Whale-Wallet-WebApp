<?php

namespace App\Http\Requests;

use App\Enums\RecurringKind;
use Illuminate\Validation\Rule;

/** Any recurring item (bill, subscription, installment, debt) for the API; the web page only edits subscriptions. */
class RecurringItemRequest extends SubscriptionRequest
{
    public function rules(): array
    {
        $mine = fn (string $table) => Rule::exists($table, 'id')->where('user_id', $this->user()->id);

        return [
            ...parent::rules(),
            'kind' => ['required', Rule::enum(RecurringKind::class)],
            'card_id' => ['nullable', $mine('cards')],
            'category_id' => ['nullable', $mine('categories')],
        ];
    }
}
