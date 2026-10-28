<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'actor' => $this->when(
                $this->relationLoaded('actor') && $this->actor,
                fn () => match ($this->actor_type) {
                    'App\\Models\\User' => ['type' => 'user', 'name' => $this->actor->name, 'email' => $this->actor->email],
                    'App\\Models\\VendorProfile' => ['type' => 'vendor', 'name' => $this->actor->store_name],
                    default => ['type' => 'system'],
                },
            ),
            'notes' => $this->notes,
            'changed_at' => $this->created_at,
        ];
    }
}
