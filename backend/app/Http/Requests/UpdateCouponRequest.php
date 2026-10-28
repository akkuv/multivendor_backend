<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($this->route('coupon'))],
            'type' => ['sometimes', 'in:PERCENTAGE,FIXED'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendor_profiles,id'],
            'min_amount' => ['sometimes', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'first_order_only' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'brand_ids' => ['sometimes', 'array'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
            'vendor_ids' => ['sometimes', 'array'],
            'vendor_ids.*' => ['integer', 'exists:vendor_profiles,id'],
        ];
    }
}
