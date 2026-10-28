<?php

namespace App\Http\Resources\V1;

use App\Enums\VendorOrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_order_number' => $this->vendor_order_number,
            'order_id' => $this->order_id,
            'vendor' => $this->when(
                $this->relationLoaded('vendor') && $this->vendor,
                fn () => new VendorResource($this->vendor),
            ),
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'tax' => (float) $this->tax,
            'fees_total' => (float) $this->fees_total,
            'shipping_charge' => (float) $this->shipping_charge,
            'cod_charge' => (float) $this->cod_charge,
            'wallet_amount' => (float) $this->wallet_amount,
            'total' => (float) $this->total,
            'status' => $this->status,
            'status_label' => VendorOrderStatus::tryFrom((string) $this->status)?->label(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'shipment' => $this->when(
                $this->relationLoaded('shipment') && $this->shipment,
                fn () => new ShipmentResource($this->shipment),
            ),
            'status_histories' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'order' => $this->when($this->relationLoaded('order') && $this->order, fn () => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'customer' => $this->when($this->order->relationLoaded('user') && $this->order->user, fn () => [
                    'id' => $this->order->user->id,
                    'name' => $this->order->user->name,
                    'email' => $this->order->user->email,
                ]),
                'payment_method' => $this->order->payment_method,
                'payment_status' => $this->order->payment_status,
                'order_status' => $this->order->order_status,
                'placed_at' => $this->order->created_at,
                'order_address' => $this->when($this->order->relationLoaded('orderAddress') && $this->order->orderAddress, fn () => new AddressResource($this->order->orderAddress)),
                'status_histories' => OrderStatusHistoryResource::collection($this->when(
                    $this->order->relationLoaded('statusHistories'),
                    fn () => $this->order->statusHistories,
                )),
            ]),
            'confirmed_at' => $this->confirmed_at,
            'packed_at' => $this->packed_at,
            'ready_to_ship_at' => $this->ready_to_ship_at,
            'shipped_at' => $this->shipped_at,
            'out_for_delivery_at' => $this->out_for_delivery_at,
            'delivered_at' => $this->delivered_at,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
