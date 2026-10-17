<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Money fields are integer centavos. */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => (bool) $this->email_verified_at,
            'ai_consent_at' => $this->ai_consent_at,
            'pay_dates' => $this->pay_dates ?? [],
            'avatar' => $this->avatar,
            'keep_receipts' => (bool) $this->keep_receipts,
        ];
    }
}
