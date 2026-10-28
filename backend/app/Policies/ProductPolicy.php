<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Policies\Concerns\ChecksVendorOwnership;
use Illuminate\Auth\Access\Response;

class ProductPolicy
{
    use ChecksVendorOwnership;

    public function viewAny(User $user): Response
    {
        return $user->isVendor()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function view(User $user, Product $product): Response
    {
        return $this->ownsVendor($user, $product->vendor_id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): Response
    {
        return $user->isVendor()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Product $product): Response
    {
        return $this->view($user, $product);
    }

    public function delete(User $user, Product $product): Response
    {
        return $this->view($user, $product);
    }
}
