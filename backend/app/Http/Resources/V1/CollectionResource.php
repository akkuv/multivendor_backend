<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'banner_image' => $this->banner_image,
            'gender' => $this->gender,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'product_ids' => $this->whenLoaded('products', fn () => $this->products->pluck('id')->all()),
            'products_count' => $this->when(isset($this->products_count), (int) $this->products_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
