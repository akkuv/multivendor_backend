<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $attributeValue = $this->route('attributeValue');

        return [
            'attribute_id' => ['sometimes', 'integer', 'exists:attributes,id'],
            'value' => ['sometimes', 'string', 'max:255', Rule::unique('attribute_values', 'value')->where('attribute_id', $this->integer('attribute_id', $attributeValue->attribute_id))->ignore($attributeValue)],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('attribute_values', 'slug')->where('attribute_id', $this->integer('attribute_id', $attributeValue->attribute_id))->ignore($attributeValue)],
            'swatch_color' => ['nullable', 'string', 'max:20'],
            'swatch_image' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
