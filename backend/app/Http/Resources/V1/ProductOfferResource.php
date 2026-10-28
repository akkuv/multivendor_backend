<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'value' => (float) $this->value,
            'min_amount' => $this->min_amount > 0 ? (float) $this->min_amount : null,
            'max_discount' => $this->max_discount !== null ? (float) $this->max_discount : null,
            'first_order_only' => (bool) $this->first_order_only,
            'starts_at' => $this->starts_at,
            'expires_at' => $this->expires_at,
        ];
    }
}
