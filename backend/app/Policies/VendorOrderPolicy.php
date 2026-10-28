<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorOrder;
use App\Policies\Concerns\ChecksVendorOwnership;
use Illuminate\Auth\Access\Response;

class VendorOrderPolicy
{
    use ChecksVendorOwnership;

    public function viewAny(User $user): Response
    {
        return $user->isVendor()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function view(User $user, VendorOrder $vendorOrder): Response
    {
        return $this->ownsVendor($user, $vendorOrder->vendor_id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, VendorOrder $vendorOrder): Response
    {
        return $this->view($user, $vendorOrder);
    }
}
