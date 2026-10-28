<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\Auth;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('order_status')) {
            return;
        }

        $actor = Auth::user();

        OrderStatusHistory::create([
            'historable_type' => Order::class,
            'historable_id' => $order->id,
            'from_status' => $order->getOriginal('order_status'),
            'to_status' => $order->order_status,
            'actor_type' => $actor ? $actor::class : null,
            'actor_id' => $actor?->id,
            'notes' => $order->status_notes ?? null,
        ]);
    }
}
