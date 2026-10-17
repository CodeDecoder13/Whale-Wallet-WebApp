<?php

namespace App\Http\Requests;

use Illuminate\Support\Arr;

/** The edited confirm card: same fields as a manual expense (pesos in, centavos out) plus the receipt reference. */
class ReceiptConfirmRequest extends TransactionRequest
{
    public function rules(): array
    {
        return [...Arr::except(parent::rules(), 'source'), 'reference' => ['nullable', 'string', 'max:100']];
    }
}
