<?php

namespace App\Http\Resources\V1;

use App\Services\Cart\CartService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pricing = app(CartService::class)->pricing($this->resource);

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $pricing['quantity'],
            'unit_price' => $pricing['unit_price'],
            'mrp' => $pricing['mrp'],
            'unit_discount' => $pricing['unit_discount'],
            'discount' => $pricing['discount'],
            'tax' => $pricing['tax'],
            'tax_rate' => $pricing['tax_rate'],
            'subtotal' => $pricing['subtotal'],
            'fees' => $pricing['fees'],
            'fees_total' => $pricing['fees_total'],
            'available_stock' => $pricing['available_stock'],
            'product' => $this->when($this->relationLoaded('product') && $this->product !== null, fn () => new ProductResource($this->product)),
            'vendor' => $this->when($this->relationLoaded('product') && $this->product?->relationLoaded('vendor') && $this->product->vendor, fn () => new StorefrontVendorResource($this->product->vendor)),
            'variant' => $this->when($this->relationLoaded('variant') && $this->variant !== null, fn () => new ProductVariantResource($this->variant)),
        ];
    }
}
