<?php

namespace App\Http\Requests\Api\V1\Wishlist;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notify_price_drops' => ['nullable', 'boolean'],
            'notify_back_in_stock' => ['nullable', 'boolean'],
        ];
    }
}
