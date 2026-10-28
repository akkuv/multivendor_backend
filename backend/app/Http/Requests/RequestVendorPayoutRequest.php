<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestVendorPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['sometimes', 'string', 'in:'.implode(',', (array) config('commission.payout_methods', ['bank_transfer', 'upi']))],
            'account_details' => ['nullable', 'array'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
