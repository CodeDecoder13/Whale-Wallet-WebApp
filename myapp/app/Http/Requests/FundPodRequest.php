<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/** Money moved from a card into a pod, with the reference number and receipt that prove it. */
class FundPodRequest extends PesoRequest
{
    protected array $pesos = ['amount'];

    public function rules(): array
    {
        return [
            'card_id' => ['required', 'integer', Rule::exists('cards', 'id')->where('user_id', $this->user()->id)],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'receipt_id' => ['nullable', 'integer', Rule::exists('receipts', 'id')->where('user_id', $this->user()->id)],
        ];
    }
}
