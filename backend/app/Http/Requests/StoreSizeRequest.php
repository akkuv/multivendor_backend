<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreSizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'size_group_id' => ['required', 'integer', 'exists:size_groups,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('sizes', 'name')->where('size_group_id', $this->integer('size_group_id'))],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('sizes', 'slug')->where('size_group_id', $this->integer('size_group_id'))],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->filled('name') && empty($this->input('slug'))) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        }
    }
}
