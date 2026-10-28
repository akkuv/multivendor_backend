<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Shared helper for the vendor-scoped policies. A vendor-scoped record is one
 * that stores a `vendor_id` referencing `vendor_profiles.id` (directly, or via
 * a parent such as `vendorOrder` / `orderItem`).
 */
trait ChecksVendorOwnership
{
    /**
     * Whether the authenticated user is the vendor account that owns a record
     * with the given vendor profile id.
     */
    protected function ownsVendor(User $user, ?int $vendorId): bool
    {
        if ($vendorId === null || ! $user->isVendor()) {
            return false;
        }

        $profileId = $user->vendorProfile?->id;

        return $profileId !== null && (int) $profileId === (int) $vendorId;
    }
}
