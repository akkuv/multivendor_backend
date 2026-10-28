<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\User;
use App\Policies\Concerns\ChecksVendorOwnership;
use Illuminate\Auth\Access\Response;

class OrderItemPolicy
{
    use ChecksVendorOwnership;

    public function view(User $user, OrderItem $orderItem): Response
    {
        return $this->ownsVendor($user, $orderItem->vendor_id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, OrderItem $orderItem): Response
    {
        return $this->view($user, $orderItem);
    }
}
