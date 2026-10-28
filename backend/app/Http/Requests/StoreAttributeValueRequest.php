<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'value' => ['required', 'string', 'max:255', Rule::unique('attribute_values', 'value')->where('attribute_id', $this->integer('attribute_id'))],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('attribute_values', 'slug')->where('attribute_id', $this->integer('attribute_id'))],
            'swatch_color' => ['nullable', 'string', 'max:20'],
            'swatch_image' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->filled('value') && empty($this->input('slug'))) {
            $this->merge(['slug' => Str::slug($this->input('value'))]);
        }
    }
}
