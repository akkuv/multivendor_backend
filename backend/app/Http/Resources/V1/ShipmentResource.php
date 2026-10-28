<?php

namespace App\Http\Resources\V1;

use App\Enums\ShipmentPickupStatus;
use App\Enums\ShipmentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof ShipmentStatus
            ? $this->status
            : ShipmentStatus::tryFrom((string) $this->status);

        $pickupStatus = $this->pickup_status instanceof ShipmentPickupStatus
            ? $this->pickup_status
            : ShipmentPickupStatus::tryFrom((string) $this->pickup_status);

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'vendor_order_id' => $this->vendor_order_id,
            'provider' => $this->provider,
            'shiprocket_order_id' => $this->shiprocket_order_id,
            'shipment_id' => $this->shipment_id,
            'tracking_number' => $this->tracking_number,
            'tracking_url' => $this->tracking_url,
            'courier_id' => $this->courier_id,
            'courier_name' => $this->courier_name,
            'pickup_location' => $this->pickup_location,
            'picker' => $this->picker,
            'pickup_status' => $pickupStatus?->value ?? $this->pickup_status,
            'pickup_status_label' => $pickupStatus?->label(),
            'status' => $status?->value ?? $this->status,
            'status_label' => $status?->label(),
            'charges' => (float) $this->charges,
            'weight' => (float) $this->weight,
            'last_event' => $this->last_event,
            'external_status' => $this->external_status,
            'error_message' => $this->error_message,
            'retry_count' => $this->retry_count,
            'label_url' => $this->label_url,
            'manifest_url' => $this->manifest_url,
            'shipped_at' => $this->shipped_at,
            'pickup_requested_at' => $this->pickup_requested_at,
            'delivered_at' => $this->delivered_at,
            'events' => ShipmentEventResource::collection($this->whenLoaded('events')),
            'order' => new OrderResource($this->whenLoaded('order')),
            'vendor_order' => new VendorOrderResource($this->whenLoaded('vendorOrder')),
        ];
    }
}
