<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Money fields are integer centavos. */
class CardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'balance' => $this->balance,
            'monthly_limit' => $this->monthly_limit,
            'currency' => $this->currency,
            'color' => $this->color,
            'spent' => $this->whenHas('spent', fn () => (int) $this->spent), // this month, when loaded with withSpent()
        ];
    }
}
