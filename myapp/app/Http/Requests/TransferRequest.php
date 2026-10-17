<?php

namespace App\Http\Requests;

class TransferRequest extends PesoRequest
{
    protected array $pesos = ['amount'];

    public function rules(): array
    {
        return [
            'from' => ['required', 'regex:/^(card|pod):\d+$/'],
            'to' => ['required', 'regex:/^(card|pod):\d+$/', 'different:from', function ($attr, $to, $fail) {
                // a transfer row hangs off a card, so pod-to-pod has nowhere to live
                if (str_starts_with($to, 'pod') && str_starts_with((string) $this->from, 'pod')) {
                    $fail(__('Pick a card on at least one side.'));
                }
            }],
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }
}
