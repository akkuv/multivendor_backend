<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\ReturnRequested;
use App\Models\VendorProfile;
use App\Services\Notifications\NotificationDispatcher;

class NotifyOnReturnRequested
{
    public function __construct(protected NotificationDispatcher $dispatcher) {}

    public function handle(ReturnRequested $event): void
    {
        $productReturn = $event->productReturn->load(['user', 'order']);

        if ($productReturn->user) {
            $this->dispatcher->send(
                $productReturn->user,
                NotificationType::ReturnRequested,
                __('Return request submitted'),
                __('Your return request has been submitted successfully.'),
                ['return_id' => $productReturn->id, 'order_number' => $productReturn->order?->order_number],
            );
        }

        $vendorIds = $productReturn->order
            ? VendorProfile::query()->whereHas('orders', fn ($q) => $q->where('order_id', $productReturn->order_id))->get()
            : collect();

        foreach ($vendorIds as $vendor) {
            if ($vendor->user) {
                $this->dispatcher->send(
                    $vendor->user,
                    NotificationType::ReturnRequestForVendor,
                    __('New return request'),
                    __('A return request has been submitted for order :order.', ['order' => $productReturn->order?->order_number]),
                    ['return_id' => $productReturn->id, 'order_number' => $productReturn->order?->order_number],
                );
            }
        }

        $this->dispatcher->notifyAdmins(
            NotificationType::AdminReturnRequest,
            __('Return request needs review'),
            __('A return request (#:id) has been submitted for review.', ['id' => $productReturn->id]),
            ['return_id' => $productReturn->id],
        );
    }
}
