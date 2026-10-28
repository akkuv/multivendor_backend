<?php

namespace App\Listeners;

use App\Events\RefundCompleted;
use App\Notifications\RefundCompletedNotification;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Order\VendorSettlementService;

class HandleRefundCompleted
{
    public function __construct(protected VendorSettlementService $settlementService) {}

    public function handle(RefundCompleted $event): void
    {
        $refund = $event->refund->load('user');

        if ($refund->user) {
            app(NotificationDispatcher::class)->deliverNow($refund->user, new RefundCompletedNotification($refund));
        }

        try {
            $this->settlementService->reverseForRefund($refund);
        } catch (\Throwable) {
        }
    }
}
