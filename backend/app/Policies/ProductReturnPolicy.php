<?php

namespace App\Policies;

use App\Models\ProductReturn;
use App\Models\User;
use App\Policies\Concerns\ChecksVendorOwnership;
use Illuminate\Auth\Access\Response;

/**
 * A return belongs to a customer (`user_id`) and, through its order item, to a
 * vendor. Both the requesting customer and the fulfilling vendor may view it;
 * only the vendor may act on it from the vendor panel.
 */
class ProductReturnPolicy
{
    use ChecksVendorOwnership;

    public function view(User $user, ProductReturn $return): Response
    {
        if ($user->isCustomer() && (int) $return->user_id === (int) $user->id) {
            return Response::allow();
        }

        return $this->ownsVendor($user, $return->orderItem?->vendor_id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, ProductReturn $return): Response
    {
        return $this->ownsVendor($user, $return->orderItem?->vendor_id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
