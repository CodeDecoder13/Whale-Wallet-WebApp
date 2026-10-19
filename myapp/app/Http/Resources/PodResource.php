<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Money fields are integer centavos. */
class PodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'target_amount' => $this->target_amount,
            'saved_amount' => $this->saved_amount,
            'monthly_amount' => $this->monthly_amount,
            'target_date' => $this->target_date?->toDateString(),
            'progress' => $this->target_amount ? min(100, intdiv($this->saved_amount * 100, $this->target_amount)) : 0,
            'card_id' => $this->card_id,
            'currency' => $this->currency,
        ];
    }
}
