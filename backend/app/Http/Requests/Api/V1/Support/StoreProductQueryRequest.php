<?php

namespace App\Http\Requests\Api\V1\Support;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductQueryRequest extends FormRequest
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
            // The subject is derived from the product name on the backend, so a
            // customer cannot label a thread with something that has nothing to
            // do with the product.
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'body' => ['required', 'string', 'min:1', 'max:4000'],
            // URL returned by the shared image upload endpoint. Optional and
            // strictly a URL so messages never carry file bytes or scripts.
            'image_url' => ['nullable', 'string', 'url', 'max:2048'],
        ];
    }
}
