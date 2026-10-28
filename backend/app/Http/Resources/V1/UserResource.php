<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'avatar' => $this->avatar,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at,
            'phone_verified_at' => $this->phone_verified_at,
            'created_at' => $this->created_at,
        ];

        if ($this->relationLoaded('vendorProfile') && $this->vendorProfile) {
            $vendorProfile = $this->vendorProfile;
            $data['vendor'] = [
                'id' => $vendorProfile->id,
                'store_name' => $vendorProfile->store_name,
                'slug' => $vendorProfile->slug,
                'logo' => $vendorProfile->logo,
                'description' => $vendorProfile->description,
                'is_approved' => $vendorProfile->is_approved,
                'status' => $vendorProfile->status,
                'commission_rate' => $vendorProfile->commission_rate,
            ];
        }

        if ($this->relationLoaded('wallet') && $this->wallet) {
            $data['wallet'] = [
                'id' => $this->wallet->id,
                'available_balance' => $this->wallet->available_balance,
                'pending_balance' => $this->wallet->pending_balance,
                'currency' => $this->wallet->currency,
            ];
        }

        return $data;
    }
}
