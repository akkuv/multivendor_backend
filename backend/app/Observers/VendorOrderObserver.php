<?php

namespace App\Observers;

use App\Models\OrderStatusHistory;
use App\Models\VendorOrder;
use Illuminate\Support\Facades\Auth;

class VendorOrderObserver
{
    public function updated(VendorOrder $vendorOrder): void
    {
        if (! $vendorOrder->wasChanged('status')) {
            return;
        }

        $actor = Auth::user();

        OrderStatusHistory::create([
            'historable_type' => VendorOrder::class,
            'historable_id' => $vendorOrder->id,
            'from_status' => $vendorOrder->getOriginal('status'),
            'to_status' => $vendorOrder->status,
            'actor_type' => $actor ? $actor::class : null,
            'actor_id' => $actor?->id,
            'notes' => $vendorOrder->status_notes ?? null,
        ]);
    }
}
