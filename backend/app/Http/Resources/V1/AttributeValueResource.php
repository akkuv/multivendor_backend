<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttributeValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attribute_id' => $this->attribute_id,
            'value' => $this->value,
            'slug' => $this->slug,
            'swatch_color' => $this->swatch_color,
            'swatch_image' => $this->swatch_image,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'attribute' => $this->when(
                $this->relationLoaded('attribute'),
                fn () => new AttributeResource($this->attribute),
            ),
        ];
    }
}
