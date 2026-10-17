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
            'kind' => ['required', 'in:bill,subscription'],
        ];
    }
}
