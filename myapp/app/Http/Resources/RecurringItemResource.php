<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Money fields are integer centavos. */
class RecurringItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'amount' => $this->amount,
            'monthly_amount' => $this->monthlyAmount(),
            'currency' => $this->currency,
            'card_id' => $this->card_id,
            'category_id' => $this->category_id,
            'cadence' => $this->cadence,
            'next_due_on' => $this->next_due_on?->toDateString(),
            'kind' => $this->kind,
        ];
    }
}
