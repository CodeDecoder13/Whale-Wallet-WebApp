<?php

namespace App\Http\Requests;

use App\Enums\TransactionSource;
use Illuminate\Validation\Rule;

class TransactionRequest extends PesoRequest
{
    protected array $pesos = ['amount'];

    public function rules(): array
    {
        $mine = fn (string $table) => Rule::exists($table, 'id')->where('user_id', $this->user()->id);

        return [
            'merchant' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'integer', 'min:1'],
            'card_id' => ['required', $mine('cards')],
            'category_id' => ['nullable', $mine('categories')],
            'occurred_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:80'],
            'source' => ['required', Rule::enum(TransactionSource::class)],
        ];
    }
}
