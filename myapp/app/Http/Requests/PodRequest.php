<?php

namespace App\Http\Requests;

class PodRequest extends PesoRequest
{
    protected array $pesos = ['target_amount', 'saved_amount', 'monthly_amount'];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'target_amount' => ['required', 'integer', 'min:1'],
            'saved_amount' => ['required', 'integer', 'min:0'],
            'monthly_amount' => ['required', 'integer', 'min:0'],
            'target_date' => ['nullable', 'date'],
        ];
    }
}
