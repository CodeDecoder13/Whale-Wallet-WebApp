<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/** A received salary is only accepted with its parsed proof screenshot and a unique reference number. */
class IncomeRequest extends PesoRequest
{
    protected array $pesos = ['amount'];

    public function rules(): array
    {
        $mine = fn (string $table) => Rule::exists($table, 'id')->where('user_id', $this->user()->id);

        return [
            'receipt_id' => ['required', 'integer', $mine('receipts')],
            'name' => ['nullable', 'string', 'max:80'],
            'amount' => ['required', 'integer', 'min:1'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['required', 'string', 'max:80', Rule::unique('income_sources', 'reference')->where('user_id', $this->user()->id)],
        ];
    }

    public function messages(): array
    {
        return ['reference.unique' => 'This reference number was already logged.'];
    }
}
