<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'icon' => $this->icon,
            'image' => $this->image,
            'sort_order' => $this->sort_order,
            'products_count' => $this->whenCounted('products_count'),
            'is_active' => $this->is_active,
            'status' => $this->status,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'parent' => $this->when(
                $this->relationLoaded('parent'),
                fn () => new CategoryResource($this->parent),
            ),
            'children' => $this->when(
                $this->relationLoaded('children'),
                fn () => CategoryResource::collection($this->children),
            ),
        ];
    }
}
