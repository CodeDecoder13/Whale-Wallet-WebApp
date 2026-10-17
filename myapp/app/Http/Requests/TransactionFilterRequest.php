<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Ledger filters, shared by the web page and GET /api/v1/transactions (see Transaction::scopeFilter). */
class TransactionFilterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'card_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
