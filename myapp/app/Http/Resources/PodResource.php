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
            'currency' => $this->currency,
        ];
    }
}
