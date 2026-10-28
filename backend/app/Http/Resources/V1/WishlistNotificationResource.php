<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'old_price' => $this->old_price,
            'new_price' => $this->new_price,
            'is_read' => (bool) $this->is_read,
            'created_at' => $this->created_at,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'images' => $this->product->images ?? [],
            ]),
        ];
    }
}
