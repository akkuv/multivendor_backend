<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Customer-side order access. Vendors never touch a customer `Order` directly —
 * they act on their own `VendorOrder` slice (see VendorOrderPolicy).
 */
class OrderPolicy
{
    public function viewAny(User $user): Response
    {
        return $user->isCustomer()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function view(User $user, Order $order): Response
    {
        return $this->ownsOrder($user, $order)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function cancel(User $user, Order $order): Response
    {
        return $this->view($user, $order);
    }

    private function ownsOrder(User $user, Order $order): bool
    {
        return $user->isCustomer() && (int) $order->user_id === (int) $user->id;
    }
}
