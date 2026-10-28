<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\OrderPlaced;
use App\Services\Notifications\NotificationDispatcher;

class NotifyAdminsOnOrderPlaced
{
    public function __construct(protected NotificationDispatcher $dispatcher) {}

    public function handle(OrderPlaced $event): void
    {
        $this->dispatcher->notifyAdmins(
            NotificationType::AdminNewOrder,
            __('New order received'),
            __('A new order :order has been placed.', ['order' => $event->order->order_number]),
            ['order_id' => $event->order->id, 'total' => (float) $event->order->total],
        );
    }
}
