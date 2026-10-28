<?php

namespace App\Jobs;

use App\Models\VendorOrder;
use App\Services\Shipping\ShippingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateShipmentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public VendorOrder $vendorOrder,
        public float $weight = 1.0,
    ) {}

    public function handle(ShippingService $shippingService): void
    {
        $shippingService->ship($this->vendorOrder, $this->weight);
    }
}
