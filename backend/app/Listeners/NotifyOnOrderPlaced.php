<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\VendorSaleNotification;
use App\Services\Notifications\NotificationDispatcher;

class NotifyOnOrderPlaced
{
    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->load(['user', 'items.vendor.user']);

        app(NotificationDispatcher::class)->deliverNow($order->user, new OrderPlacedNotification($order));

        foreach ($order->items as $item) {
            $vendorUser = $item->vendor?->user;
            if ($vendorUser) {
                app(NotificationDispatcher::class)->deliverNow($vendorUser, new VendorSaleNotification($item));
            }
        }
    }
}
