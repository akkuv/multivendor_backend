<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Events\PaymentSucceeded;
use App\Services\Notifications\NotificationDispatcher;

class NotifyOnPaymentResult
{
    public function __construct(protected NotificationDispatcher $dispatcher) {}

    public function handle(PaymentSucceeded $event): void
    {
        $order = $event->order->load('user');

        if (! $order->user) {
            return;
        }

        $isPaid = $order->payment_status === PaymentStatus::Paid->value;

        $this->dispatcher->send(
            $order->user,
            $isPaid ? NotificationType::PaymentSuccess : NotificationType::PaymentFailure,
            $isPaid ? __('Payment successful') : __('Payment failed'),
            $isPaid
                ? __('Your payment for order :order was successful.', ['order' => $order->order_number])
                : __('Your payment for order :order could not be completed.', ['order' => $order->order_number]),
            ['order_id' => $order->id, 'order_number' => $order->order_number, 'amount' => (float) $order->total],
        );
    }
}
