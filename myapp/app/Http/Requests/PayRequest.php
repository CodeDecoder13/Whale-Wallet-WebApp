<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PayRequest extends PesoRequest
{
    protected array $pesos = ['amount'];

    public function rules(): array
    {
        return [
            'card_id' => ['required', 'integer', Rule::exists('cards', 'id')->where('user_id', $this->user()->id)],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
