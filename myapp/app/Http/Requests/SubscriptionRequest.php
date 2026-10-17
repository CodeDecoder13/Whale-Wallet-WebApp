<?php

namespace App\Http\Requests;

class SubscriptionRequest extends PesoRequest
{
    protected array $pesos = ['amount'];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'integer', 'min:1'],
            'cadence' => ['required', 'in:weekly,biweekly,monthly,quarterly,yearly'],
            'next_due_on' => ['required', 'date'],
            'kind' => ['required', 'in:bill,subscription,debt'],
            'logo' => ['nullable', 'string', 'max:150000', 'starts_with:data:image/png;base64,,data:image/jpeg;base64,,data:image/webp;base64,'],
            // a loan is paid off in 1, 2 or 3 payments, or over 6 or 12 months
            'installments' => ['nullable', 'required_if:kind,debt', 'in:1,2,3,6,12'],
        ];
    }
}
