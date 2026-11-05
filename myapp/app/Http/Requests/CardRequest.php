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
            'balance' => ['required', 'integer', 'between:-100000000000,100000000000'], // centavos: a credit card balance can be negative
            'monthly_limit' => ['nullable', 'integer', 'between:1,100000000000'],
            'color' => ['nullable', 'string', 'max:20'],
            'account_name' => ['nullable', 'string', 'max:80'],
            'account_number' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 -]+$/'],
            // the browser shrinks the QR to a 480px PNG; anything else is rejected
            'qr' => ['nullable', 'string', 'max:400000', 'starts_with:data:image/png;base64,'],
        ];
    }
}
