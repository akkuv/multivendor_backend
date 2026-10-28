<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'vendor_id' => $this->vendor_id,
            'rating' => (int) $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'images' => $this->images ?? [],
            'is_verified' => (bool) $this->is_verified,
            'is_approved' => (bool) $this->is_approved,
            'is_hidden' => (bool) $this->is_hidden,
            'reported_at' => $this->reported_at,
            'report_reason' => $this->report_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'avatar' => $this->user?->avatar,
            ]),
            'product' => $this->when($this->relationLoaded('product') && $this->product !== null, fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'images' => $this->product->images ?? [],
            ]),
            'vendor' => $this->whenLoaded('vendor', fn () => [
                'id' => $this->vendor?->id,
                'store_name' => $this->vendor?->store_name,
            ]),
        ];
    }
}
