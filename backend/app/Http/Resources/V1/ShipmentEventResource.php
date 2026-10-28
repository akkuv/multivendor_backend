<?php

namespace App\Http\Resources\V1;

use App\Enums\ShipmentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = ShipmentStatus::tryFrom((string) $this->status);

        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'status' => $status?->value ?? $this->status,
            'status_label' => $status?->label(),
            'location' => $this->location,
            'description' => $this->description,
            'is_webhook' => $this->is_webhook,
            'occurred_at' => $this->occurred_at,
        ];
    }
}
