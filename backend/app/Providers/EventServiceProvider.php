<?php

namespace App\Providers;

use App\Events\OrderPlaced;
use App\Events\OrderShipped;
use App\Events\PaymentSucceeded;
use App\Events\RefundCompleted;
use App\Events\ReturnRequested;
use App\Listeners\HandleRefundCompleted;
use App\Listeners\NotifyAdminsOnOrderPlaced;
use App\Listeners\NotifyOnOrderPlaced;
use App\Listeners\NotifyOnOrderShipped;
use App\Listeners\NotifyOnPaymentResult;
use App\Listeners\NotifyOnReturnRequested;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderPlaced::class => [
            NotifyOnOrderPlaced::class,
            NotifyAdminsOnOrderPlaced::class,
        ],
        OrderShipped::class => [
            NotifyOnOrderShipped::class,
        ],
        PaymentSucceeded::class => [
            NotifyOnPaymentResult::class,
        ],
        RefundCompleted::class => [
            HandleRefundCompleted::class,
        ],
        ReturnRequested::class => [
            NotifyOnReturnRequested::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
