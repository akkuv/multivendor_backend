<?php

namespace App\Http\Requests;

use App\Enums\ReturnStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReturnStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_column(ReturnStatus::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'auto_refund' => ['sometimes', 'boolean'],
        ];
    }
}
