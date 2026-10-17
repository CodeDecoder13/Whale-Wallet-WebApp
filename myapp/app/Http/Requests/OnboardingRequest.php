<?php

namespace App\Http\Requests;

use App\Enums\CardType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnboardingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->is('api/*')) {
            return; // the API already sends centavos
        }

        $in = $this->all();
        $in['income'] = PesoRequest::centavos($in['income'] ?? null);
        if (is_array($in['card'] ?? null)) {
            $in['card']['balance'] = PesoRequest::centavos($in['card']['balance'] ?? null);
        }
        if (is_array($in['pod'] ?? null)) {
            $in['pod']['target_amount'] = PesoRequest::centavos($in['pod']['target_amount'] ?? null);
        }
        foreach (is_array($in['bills'] ?? null) ? $in['bills'] : [] as $i => $bill) {
            if (is_array($bill)) {
                $in['bills'][$i]['amount'] = PesoRequest::centavos($bill['amount'] ?? null);
            }
        }
        $this->replace($in);
    }

    public function rules(): array
    {
        return [
            'income' => ['required', 'integer', 'min:1'],
            'pay_dates' => ['required', 'array', 'min:1', 'max:2'],
            'pay_dates.*' => ['integer', 'between:1,31'],
            'bills' => ['nullable', 'array', 'max:30'],
            'bills.*.name' => ['required', 'string', 'max:80'],
            'bills.*.amount' => ['required', 'integer', 'min:1'],
            'bills.*.day' => ['required', 'integer', 'between:1,31'],
            'card.name' => ['required', 'string', 'max:80'],
            'card.type' => ['required', Rule::enum(CardType::class)],
            'card.balance' => ['required', 'integer'],
            'card.color' => ['nullable', 'string', 'max:20'],
            'pod' => ['nullable', 'array'],
            'pod.name' => ['nullable', 'string', 'max:80'],
            'pod.target_amount' => ['required_with:pod.name', 'nullable', 'integer', 'min:1'],
        ];
    }
}
