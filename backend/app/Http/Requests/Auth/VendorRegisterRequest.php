<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VendorRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],

            'store_name' => ['required', 'string', 'max:255'],
            'address_line1' => ['required', 'string', 'max:500'],
            'address_line2' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'pincode' => ['nullable', 'string', 'max:20'],

            'gstin' => ['required', 'string', 'max:20'],
            'pan' => ['required', 'string', 'max:20'],
            'bank_account_name' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'string', 'max:30'],
            'bank_ifsc' => ['required', 'string', 'max:15'],
            'bank_name' => ['required', 'string', 'max:255'],
            'upi_id' => ['nullable', 'string', 'max:255'],
            'business_documents' => ['nullable', 'array'],
            'business_documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
