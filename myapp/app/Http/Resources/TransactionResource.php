<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Money fields are integer centavos. */
class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'merchant' => $this->merchant,
            'card_id' => $this->card_id,
            'card_name' => $this->card?->name,
            'category_id' => $this->category_id,
            'category_name' => $this->category?->name,
            'to_card_id' => $this->to_card_id,
            'to_pod_id' => $this->to_pod_id,
            'from_pod_id' => $this->from_pod_id,
            'occurred_at' => $this->occurred_at,
            'note' => $this->note,
            'source' => $this->source,
            'receipt_id' => $this->receipt_id,
            'reference' => $this->reference,
        ];
    }
}
