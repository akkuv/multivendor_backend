<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('attributes', 'slug')],
            'type' => ['nullable', Rule::in(['SELECT', 'RADIO', 'BUTTON', 'COLOR'])],
            'description' => ['nullable', 'string'],
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
