<?php

namespace App\Http\Resources\V1;

use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'tax' => (float) $this->tax,
            'fees_total' => (float) $this->fees_total,
            'shipping_charge' => (float) $this->shipping_charge,
            'cod_charge' => (float) $this->cod_charge,
            'wallet_amount' => (float) $this->wallet_amount,
            'total' => (float) $this->total,
            'order_status' => $this->order_status,
            'order_status_label' => OrderStatus::tryFrom((string) $this->order_status)?->label(),
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'razorpay_order_id' => $this->razorpay_order_id,
            'razorpay_payment_id' => $this->razorpay_payment_id,
            'paid_at' => $this->paid_at,
            'cancelled_at' => $this->cancelled_at,
            'placed_at' => $this->created_at,
            'created_at' => $this->created_at,
            'notes' => $this->notes,
            'user' => $this->when(
                $this->relationLoaded('user') && $this->user,
                fn () => new UserResource($this->user),
            ),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'vendor_orders' => VendorOrderResource::collection($this->whenLoaded('vendorOrders')),
            'status_histories' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'shipping_address' => $this->when(
                $this->relationLoaded('shippingAddress') && $this->shippingAddress,
                fn () => new AddressResource($this->shippingAddress),
            ),
            'order_address' => $this->when(
                $this->relationLoaded('orderAddress') && $this->orderAddress,
                fn () => new AddressResource($this->orderAddress),
            ),
            'shipment' => $this->when(
                $this->relationLoaded('shipment') && $this->shipment,
                fn () => new ShipmentResource($this->shipment),
            ),
        ];
    }
}
