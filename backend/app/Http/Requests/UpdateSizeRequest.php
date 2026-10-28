<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $size = $this->route('size');

        return [
            'size_group_id' => ['sometimes', 'integer', 'exists:size_groups,id'],
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('sizes', 'name')->where('size_group_id', $this->integer('size_group_id', $size->size_group_id))->ignore($size)],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('sizes', 'slug')->where('size_group_id', $this->integer('size_group_id', $size->size_group_id))->ignore($size)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
