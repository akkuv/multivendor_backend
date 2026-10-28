<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveWishlistToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
