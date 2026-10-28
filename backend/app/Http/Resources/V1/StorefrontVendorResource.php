<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StorefrontVendorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_name' => $this->store_name,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo' => $this->logo,
            'banner' => $this->banner,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'is_approved' => $this->is_approved,
            'rating_avg' => $this->averageRating(),
            'rating_count' => $this->reviewCount(),
            'products_count' => $this->when(isset($this->products_count), (int) $this->products_count),
        ];
    }
}
