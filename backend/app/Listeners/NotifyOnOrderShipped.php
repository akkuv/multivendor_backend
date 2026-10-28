<?php

namespace App\Listeners;

use App\Events\OrderShipped;
use App\Notifications\ShipmentNotification;
use App\Services\Notifications\NotificationDispatcher;

class NotifyOnOrderShipped
{
    public function handle(OrderShipped $event): void
    {
        $shipment = $event->shipment->load('order.user');

        if ($shipment->order?->user) {
            app(NotificationDispatcher::class)->deliverNow($shipment->order->user, new ShipmentNotification($shipment));
        }
    }
}
