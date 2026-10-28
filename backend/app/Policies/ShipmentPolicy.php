<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;
use App\Policies\Concerns\ChecksVendorOwnership;
use Illuminate\Auth\Access\Response;

class ShipmentPolicy
{
    use ChecksVendorOwnership;

    public function viewAny(User $user): Response
    {
        return $user->isVendor()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function view(User $user, Shipment $shipment): Response
    {
        return $this->ownsVendor($user, $shipment->vendorOrder?->vendor_id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Shipment $shipment): Response
    {
        return $this->view($user, $shipment);
    }
}
