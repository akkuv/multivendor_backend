<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'collection_ids' => ['nullable', 'array'],
            'collection_ids.*' => ['integer', 'exists:collections,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'highlights' => ['nullable', 'array'],
            'highlights.*' => ['nullable', 'string', 'max:1000'],
            'specifications' => ['nullable', 'array'],
            'specifications.*' => ['nullable', 'string'],
            'manufacturer_info' => ['nullable', 'array'],
            'tax_class' => ['nullable', 'string', 'max:50'],
            'gst' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'hsn_code' => ['nullable', 'string', 'max:20'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'mrp' => ['nullable', 'numeric', 'gte:price'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'sku' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:DRAFT,PUBLISHED,ARCHIVED'],
            'featured' => ['sometimes', 'boolean'],
            'best_seller' => ['sometimes', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'meta_keywords' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'is_returnable' => ['sometimes', 'boolean'],
            'return_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
            'return_policy' => ['sometimes', 'nullable', 'string', 'max:2000'],

            'images' => ['nullable', 'array'],
            'images.*.url' => ['required_with:images', 'string', 'max:2048'],
            'images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'images.*.is_primary' => ['sometimes', 'boolean'],
            'images.*.sort_order' => ['sometimes', 'integer', 'min:0'],

            'videos' => ['nullable', 'array'],
            'videos.*.url' => ['required_with:videos', 'string', 'max:2048'],
            'videos.*.provider' => ['nullable', 'string', 'max:20'],
            'videos.*.thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'videos.*.sort_order' => ['sometimes', 'integer', 'min:0'],

            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
            'variants.*.price' => ['sometimes', 'numeric', 'min:0'],
            'variants.*.mrp' => ['nullable', 'numeric', 'gte:variants.*.price'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.status' => ['nullable', 'in:ACTIVE,INACTIVE'],
            'variants.*.weight' => ['nullable', 'numeric', 'min:0'],
            'variants.*.length' => ['nullable', 'numeric', 'min:0'],
            'variants.*.width' => ['nullable', 'numeric', 'min:0'],
            'variants.*.height' => ['nullable', 'numeric', 'min:0'],
            'variants.*.attributes' => ['nullable', 'array'],
            'variants.*.images' => ['nullable', 'array'],
            'variants.*.images.*' => ['nullable', 'string', 'max:2048'],

            'fees' => ['nullable', 'array', 'max:20'],
            'fees.*.name' => ['required_with:fees', 'string', 'max:191'],
            'fees.*.amount' => ['required_with:fees', 'numeric', 'min:0', 'max:999999'],

            'faqs' => ['nullable', 'array', 'max:30'],
            'faqs.*.question' => ['required_with:faqs', 'string', 'max:500'],
            'faqs.*.answer' => ['required_with:faqs', 'string', 'max:5000'],
            'faqs.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
