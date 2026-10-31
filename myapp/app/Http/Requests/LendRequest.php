<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/** Cash lent to a person: who, how much, which card sent it (with proof), when it is due and when to remind them. */
class LendRequest extends PesoRequest
{
    protected array $pesos = ['amount'];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:120'],
            'card_id' => ['required', 'integer', Rule::exists('cards', 'id')->where('user_id', $this->user()->id)],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'], // the day the money was sent
            'due_on' => ['required', 'date', 'after_or_equal:today'],
            'remind_before' => ['required', 'integer', 'between:0,30'],
            'reference' => ['nullable', 'string', 'max:100'],
            'receipt_id' => ['nullable', 'integer', Rule::exists('receipts', 'id')->where('user_id', $this->user()->id)],
        ];
    }
}
