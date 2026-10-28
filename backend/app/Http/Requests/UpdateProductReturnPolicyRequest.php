<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductReturnPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_returnable' => ['required', 'boolean'],
            'return_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'return_policy' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
