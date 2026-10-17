<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Money fields are integer centavos. */
class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expo_push_token' => $this->expo_push_token,
            'platform' => $this->platform,
            'last_seen_at' => $this->last_seen_at,
        ];
    }
}
