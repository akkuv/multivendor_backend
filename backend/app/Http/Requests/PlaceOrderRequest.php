<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer', 'exists:addresses,id'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['sometimes', 'in:razorpay,wallet,cod'],
            'wallet_amount' => ['nullable', 'numeric', 'min:0'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }
}
