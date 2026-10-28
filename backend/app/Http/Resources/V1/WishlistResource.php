<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'price_at_add' => $this->price_at_add,
            'notify_price_drops' => (bool) $this->notify_price_drops,
            'notify_back_in_stock' => (bool) $this->notify_back_in_stock,
            'added_at' => $this->created_at,
        ];
    }
}
