<?php

namespace App\Services\Review;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    /**
     * Create or update a review for a product the user actually purchased and
     * received. A verified-purchase badge is stamped when a qualifying
     * delivered order item exists.
     */
    public function submit(User $user, array $data, Product $product): Review
    {
        if (! $product->isLive()) {
            throw new \InvalidArgumentException('This product is no longer available.');
        }

        $orderItem = OrderItem::query()
            ->where('product_id', $product->id)
            ->whereHas('order', function ($q) use ($user): void {
                $q->where('user_id', $user->id)
                    ->where('order_status', OrderStatus::Delivered->value);
            })
            ->latest()
            ->first();

        if (! $orderItem) {
            throw new \InvalidArgumentException('You can only review products you have purchased and received.');
        }

        $existing = Review::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing && ! config('reviews.allow_updates')) {
            throw new \DomainException('You have already reviewed this product.');
        }

        $approved = (bool) config('reviews.auto_approve');

        return DB::transaction(function () use ($user, $product, $orderItem, $data, $approved, $existing): Review {
            $values = [
                'rating' => (int) $data['rating'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
                'images' => $data['images'] ?? [],
                'is_verified' => true,
                'vendor_id' => $orderItem->vendor_id,
                'is_approved' => $approved,
                'is_hidden' => false,
            ];

            $review = $existing
                ? tap($existing)->update($values)
                : $user->reviews()->create(array_merge($values, [
                    'product_id' => $product->id,
                    'order_item_id' => $orderItem->id,
                ]));

            return $review->load('user', 'product');
        });
    }

    public function deleteOwnedBy(User $user, Review $review): void
    {
        if ($review->user_id !== $user->id) {
            throw new \InvalidArgumentException('You are not authorized to delete this review.');
        }

        $review->delete();
    }
}
