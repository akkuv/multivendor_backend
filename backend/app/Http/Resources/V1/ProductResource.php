<?php

namespace App\Http\Resources\V1;

use App\Models\ProductFee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendor_id,
            'category_id' => $this->category_id,
            'subcategory_id' => $this->subcategory_id,
            'brand_id' => $this->brand_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'highlights' => $this->highlights ?? [],
            'specifications' => $this->specifications ?? [],
            'manufacturer_info' => $this->manufacturer_info ?? null,
            'tax_class' => $this->tax_class,
            'gst' => $this->gst !== null ? (float) $this->gst : null,
            'hsn_code' => $this->hsn_code,
            'price' => (float) $this->price,
            'mrp' => $this->mrp !== null ? (float) $this->mrp : null,
            'discount_percent' => $this->mrp && $this->mrp > $this->price
                ? round((($this->mrp - $this->price) / $this->mrp) * 100, 1)
                : 0,
            'rating' => [
                'average' => $this->rating_avg !== null ? round((float) $this->rating_avg, 2) : null,
                'count' => (int) ($this->rating_count ?? 0),
            ],
            'stock' => $this->stock,
            'available_stock' => $this->availableStock(),
            'sku' => $this->sku,
            'images' => $this->images ?? [],
            'attributes' => $this->attributes ?? [],
            'is_active' => $this->is_active,
            'status' => $this->status,
            'approval_status' => $this->approval_status,
            'featured' => $this->featured,
            'best_seller' => $this->best_seller,
            'is_returnable' => (bool) $this->is_returnable,
            'return_days' => $this->return_days !== null ? (int) $this->return_days : null,
            'return_policy' => $this->return_policy,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'approved_at' => $this->approved_at,
            'rejected_at' => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'vendor' => $this->when($this->relationLoaded('vendor'), fn () => new StorefrontVendorResource($this->vendor)),
            'category' => $this->when($this->relationLoaded('category'), fn () => new CategoryResource($this->category)),
            'subcategory' => $this->when($this->relationLoaded('subcategory'), fn () => new CategoryResource($this->subcategory)),
            'brand' => $this->when($this->relationLoaded('brand'), fn () => new BrandResource($this->brand)),
            'collection_ids' => $this->when(
                $this->relationLoaded('collections'),
                fn () => $this->collections->pluck('id')->all(),
            ),
            'collections' => $this->when(
                $this->relationLoaded('collections'),
                fn () => CollectionResource::collection($this->collections),
            ),
            'videos' => $this->when(
                $this->relationLoaded('videos'),
                fn () => ProductVideoResource::collection($this->videos),
            ),
            'variants' => $this->when(
                $this->relationLoaded('variants'),
                fn () => ProductVariantResource::collection($this->variants),
            ),
            'fees' => $this->when(
                $this->relationLoaded('fees'),
                fn () => $this->fees->map(fn (ProductFee $fee): array => [
                    'id' => $fee->id,
                    'name' => $fee->name,
                    'amount' => (float) $fee->amount,
                ])->values()->all(),
            ),
            'faqs' => $this->when(
                $this->relationLoaded('faqs'),
                fn () => ProductFaqResource::collection($this->faqs),
            ),
        ];
    }
}
