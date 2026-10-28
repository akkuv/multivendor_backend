<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddWalletMoneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:100000'],
        ];
    }
}
