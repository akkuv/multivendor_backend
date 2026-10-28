<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A customer's saved delivery address. Unlike the vendor-scoped policies, a
 * foreign address is rejected with 403 (the record exists — the caller simply
 * is not its owner), which is the historical behaviour of AddressController.
 */
class AddressPolicy
{
    public function viewAny(User $user): Response
    {
        return $user->isCustomer()
            ? Response::allow()
            : Response::deny();
    }

    public function view(User $user, Address $address): Response
    {
        return $this->ownsAddress($user, $address)
            ? Response::allow()
            : Response::deny();
    }

    public function create(User $user): Response
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Address $address): Response
    {
        return $this->view($user, $address);
    }

    public function delete(User $user, Address $address): Response
    {
        return $this->view($user, $address);
    }

    private function ownsAddress(User $user, Address $address): bool
    {
        return $user->isCustomer() && (int) $address->user_id === (int) $user->id;
    }
}
