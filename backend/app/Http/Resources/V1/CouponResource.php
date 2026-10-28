<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendor_id,
            'code' => $this->code,
            'type' => $this->type,
            'value' => (float) $this->value,
            'min_amount' => (float) $this->min_amount,
            'max_discount' => $this->max_discount !== null ? (float) $this->max_discount : null,
            'starts_at' => $this->starts_at,
            'expires_at' => $this->expires_at,
            'usage_limit' => $this->usage_limit,
            'per_user_limit' => $this->per_user_limit,
            'first_order_only' => $this->first_order_only,
            'used_count' => $this->used_count,
            'is_active' => $this->is_active,
            'product_ids' => $this->when($this->relationLoaded('products'), fn () => $this->products->pluck('id')),
            'category_ids' => $this->when($this->relationLoaded('categories'), fn () => $this->categories->pluck('id')),
            'brand_ids' => $this->when($this->relationLoaded('brands'), fn () => $this->brands->pluck('id')),
            'vendor_ids' => $this->when($this->relationLoaded('vendors'), fn () => $this->vendors->pluck('id')),
        ];
    }
}
