<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'price' => (float) $this->price,
            'mrp' => $this->mrp !== null ? (float) $this->mrp : null,
            'cost_price' => $this->cost_price !== null ? (float) $this->cost_price : null,
            'discount' => (float) $this->discount,
            'stock' => $this->stock,
            'reserved_stock' => $this->reserved_stock,
            'available_stock' => $this->availableStock(),
            'weight' => $this->weight !== null ? (float) $this->weight : null,
            'length' => $this->length !== null ? (float) $this->length : null,
            'width' => $this->width !== null ? (float) $this->width : null,
            'height' => $this->height !== null ? (float) $this->height : null,
            'status' => $this->status,
            'attributes' => $this->attributes ?? [],
            'images' => $this->images ?? [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
