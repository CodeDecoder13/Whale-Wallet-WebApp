<?php

namespace App\Http\Requests;

use App\Enums\CardType;
use Illuminate\Validation\Rule;

class CardRequest extends PesoRequest
{
    protected array $pesos = ['balance', 'monthly_limit'];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::enum(CardType::class)],
            'balance' => ['required', 'integer'],
            'monthly_limit' => ['nullable', 'integer', 'min:1'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
