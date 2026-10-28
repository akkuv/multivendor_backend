<?php

namespace App\Services\Review;

use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorReview;
use Illuminate\Support\Facades\DB;

class VendorReviewService
{
    /**
     * Create or update a review for a vendor the user actually purchased from
     * and received goods from (delivered/returned/refunded vendor order).
     */
    public function submit(User $user, array $data, VendorProfile $vendor): VendorReview
    {
        if (! VendorReview::customerCompletedOrder($vendor, $user)) {
            throw new \InvalidArgumentException('You can only review vendors you have ordered from.');
        }

        $existing = VendorReview::query()
            ->where('user_id', $user->id)
            ->where('vendor_id', $vendor->id)
            ->first();

        if ($existing && ! config('reviews.allow_updates')) {
            throw new \DomainException('You have already reviewed this vendor.');
        }

        $approved = (bool) config('reviews.auto_approve');

        return DB::transaction(function () use ($user, $vendor, $data, $approved, $existing): VendorReview {
            $values = [
                'rating' => (int) $data['rating'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
                'is_verified' => true,
                'is_approved' => $approved,
                'is_hidden' => false,
            ];

            $review = $existing
                ? tap($existing)->update($values)
                : $user->vendorReviews()->create(array_merge($values, [
                    'vendor_id' => $vendor->id,
                ]));

            return $review->load('user', 'vendor');
        });
    }

    public function deleteOwnedBy(User $user, VendorReview $review): void
    {
        if ($review->user_id !== $user->id) {
            throw new \InvalidArgumentException('You are not authorized to delete this review.');
        }

        $review->delete();
    }
}
