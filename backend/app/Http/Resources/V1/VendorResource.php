<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorResource extends JsonResource
{
    /**
     * @param  array<string, mixed>|int|null  $extra
     */
    public function __construct($resource, protected array|int|null $extra = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'store_name' => $this->store_name,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo' => $this->logo,
            'banner' => $this->banner,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'pincode' => $this->pincode,
            'gstin' => $this->gstin,
            'commission_rate' => $this->commission_rate,
            'shipping_charge' => $this->shipping_charge,
            'free_shipping_threshold' => $this->free_shipping_threshold,
            'is_approved' => $this->is_approved,
            'status' => $this->status,
            'created_at' => $this->created_at,
            ...$this->whenVendorLoaded(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function whenVendorLoaded(): array
    {
        $data = [];

        if ($this->relationLoaded('user') && $this->user) {
            $data['user'] = [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'is_active' => $this->user->is_active,
            ];
        }

        if (is_array($this->extra)) {
            foreach ($this->extra as $key => $value) {
                $data[$key] = $value;
            }
        }

        if (! empty($data['wallet']) && $this->wallet) {
            $data['wallet'] = [
                'available_balance' => $this->wallet->available_balance,
                'pending_balance' => $this->wallet->pending_balance,
                'held_balance' => $this->wallet->held_balance,
                'paid_total' => $this->wallet->paid_total,
                'currency' => $this->wallet->currency,
            ];
        }

        return $data;
    }
}
