<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/** Income comes with its parsed proof screenshot and a unique reference number, or is typed in by hand (then both are optional). */
class IncomeRequest extends PesoRequest
{
    protected array $pesos = ['amount'];

    public function rules(): array
    {
        $mine = fn (string $table) => Rule::exists($table, 'id')->where('user_id', $this->user()->id);

        return [
            'receipt_id' => ['nullable', 'integer', $mine('receipts')],
            'kind' => ['nullable', 'in:salary,side'],
            'card_id' => ['nullable', Rule::requiredIf(fn () => $this->user()->cards()->exists()), $mine('cards')],
            'name' => ['nullable', 'string', 'max:80'],
            'amount' => ['required', 'integer', 'min:1'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['required_with:receipt_id', 'nullable', 'string', 'max:80', Rule::unique('income_sources', 'reference')->where('user_id', $this->user()->id)],
        ];
    }

    public function messages(): array
    {
        return ['reference.unique' => 'This reference number was already logged.'];
    }
}
