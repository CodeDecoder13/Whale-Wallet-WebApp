<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PodRequest extends PesoRequest
{
    protected array $pesos = ['target_amount', 'saved_amount', 'monthly_amount'];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'target_amount' => ['required', 'integer', 'between:1,100000000000'], // centavos: up to ₱1 billion
            'saved_amount' => ['required', 'integer', 'between:0,100000000000'],
            'monthly_amount' => ['required', 'integer', 'between:0,100000000000'],
            'target_date' => ['nullable', 'date'],
            'card_id' => ['nullable', 'integer', Rule::exists('cards', 'id')->where('user_id', $this->user()->id)],
        ];
    }
}
