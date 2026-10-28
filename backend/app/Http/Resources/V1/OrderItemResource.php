<?php

namespace App\Http\Resources\V1;

use App\Enums\ReturnStatus;
use App\Models\ProductReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'vendor_id' => $this->vendor_id,
            'product_name' => $this->product_name,
            'product_image' => $this->product_image,
            'unit_price' => (float) $this->unit_price,
            'quantity' => $this->quantity,
            'total' => (float) $this->total,
            'fees_total' => (float) ($this->fees_total ?? 0),
            'fees' => $this->fees ?? [],
            'commission_rate' => (float) $this->commission_rate,
            'commission_amount' => (float) $this->commission_amount,
            'status' => $this->status,
            'return_policy' => $this->when(
                $this->relationLoaded('product') && $this->product,
                fn () => [
                    'is_returnable' => (bool) $this->product->is_returnable,
                    'return_days' => $this->product->return_days !== null ? (int) $this->product->return_days : null,
                    'return_policy' => $this->product->return_policy,
                ],
            ),
            'has_active_return' => $this->relationLoaded('productReturns')
                ? $this->productReturns->contains(
                    fn (ProductReturn $productReturn) => $productReturn->status !== ReturnStatus::Closed->value
                )
                : $this->productReturns()->whereNotIn('status', [ReturnStatus::Closed->value])->exists(),
            'vendor' => $this->when(
                $this->relationLoaded('vendor') && $this->vendor,
                fn () => new VendorResource($this->vendor),
            ),
        ];
    }
}
