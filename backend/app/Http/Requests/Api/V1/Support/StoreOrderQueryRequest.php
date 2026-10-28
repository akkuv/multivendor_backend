<?php

namespace App\Http\Requests\Api\V1\Support;

use App\Models\VendorOrder;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderQueryRequest extends FormRequest
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
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'vendor_id' => [
                'required',
                'integer',
                'exists:vendor_profiles,id',
                // An admin only asks a vendor about an order the vendor actually
                // fulfilled, otherwise the query would land on the wrong desk.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $orderId = $this->integer('order_id');

                    if ($orderId !== null && ! VendorOrder::query()
                        ->where('order_id', $orderId)
                        ->where('vendor_id', $value)
                        ->exists()) {
                        $fail(__('This vendor is not part of this order.'));
                    }
                },
            ],
            'subject' => ['required', 'string', 'min:3', 'max:255'],
            'body' => ['required', 'string', 'min:1', 'max:4000'],
        ];
    }
}
