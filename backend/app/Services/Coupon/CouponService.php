<?php

namespace App\Services\Coupon;

use App\Enums\CouponErrorCode;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CouponService
{
    /**
     * Validate a coupon against a list of cart lines and, optionally, a user.
     * Lines must contain product_id, vendor_id, category_id, brand_id and
     * subtotal keys. Returns the coupon together with the eligible subset and
     * effective discount, or throws a CouponException describing the failure.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{coupon: Coupon, eligible_items: array, eligible_subtotal: float, discount: float}
     */
    public function apply(string $code, array $items, ?User $user = null): array
    {
        $coupon = $this->find($code);

        $this->assertActive($coupon);
        $this->assertTiming($coupon);

        $eligible = $this->restrictItems($coupon, $items);
        $eligibleSubtotal = round((float) array_sum(array_column($eligible, 'subtotal')), 2);

        if (empty($eligible) || $eligibleSubtotal <= 0) {
            $this->throwNotApplicable($coupon, $items);
        }

        $this->assertMinimumOrder($coupon, $eligibleSubtotal);
        $this->assertUsageLimit($coupon);

        if ($user) {
            $this->assertFirstOrderOnly($coupon, $user);
            $this->assertUserLimit($coupon, $user);
        }

        return [
            'coupon' => $coupon,
            'eligible_items' => $eligible,
            'eligible_subtotal' => $eligibleSubtotal,
            'discount' => $this->discountAmount($coupon, $eligibleSubtotal),
        ];
    }

    public function discountAmount(Coupon $coupon, float $subtotal): float
    {
        $discount = $coupon->type === 'FIXED'
            ? (float) $coupon->value
            : round($subtotal * ((float) $coupon->value / 100), 2);

        if ($coupon->max_discount !== null) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        return round(min($discount, $subtotal), 2);
    }

    public function userUsageCount(Coupon $coupon, User $user): int
    {
        return CouponUsage::where('coupon_id', $coupon->id)
            ->where('user_id', $user->id)
            ->count();
    }

    public function markUsed(Coupon $coupon, User $user, int $orderId, float $discount): void
    {
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'order_id' => $orderId,
            'user_id' => $user->id,
            'discount_amount' => $discount,
        ]);

        $coupon->increment('used_count');
    }

    protected function find(string $code): Coupon
    {
        $coupon = Coupon::where('code', strtoupper($code))->first();

        if (! $coupon) {
            throw new CouponException(CouponErrorCode::CouponNotFound, 'Invalid coupon code.');
        }

        return $coupon;
    }

    protected function assertActive(Coupon $coupon): void
    {
        if (! $coupon->is_active) {
            throw new CouponException(CouponErrorCode::CouponInactive, 'This coupon is not active.');
        }
    }

    protected function assertTiming(Coupon $coupon): void
    {
        $now = now();

        if ($coupon->starts_at && $now->lt(Carbon::parse($coupon->starts_at))) {
            throw new CouponException(CouponErrorCode::CouponNotStarted, 'This coupon is not yet active.');
        }

        if ($coupon->expires_at && $now->gt(Carbon::parse($coupon->expires_at))) {
            throw new CouponException(CouponErrorCode::CouponExpired, 'This coupon has expired.');
        }
    }

    protected function assertMinimumOrder(Coupon $coupon, float $eligibleSubtotal): void
    {
        if ((float) $coupon->min_amount > $eligibleSubtotal) {
            throw new CouponException(
                CouponErrorCode::MinimumOrderNotMet,
                'Minimum order value of '.number_format((float) $coupon->min_amount, 2).' not met for this coupon.',
            );
        }
    }

    protected function assertUsageLimit(Coupon $coupon): void
    {
        if ($coupon->usage_limit !== null && (int) $coupon->used_count >= (int) $coupon->usage_limit) {
            throw new CouponException(CouponErrorCode::UsageLimitReached, 'This coupon has reached its usage limit.');
        }
    }

    protected function assertFirstOrderOnly(Coupon $coupon, User $user): void
    {
        if ($coupon->first_order_only && $user->orders()->exists()) {
            throw new CouponException(CouponErrorCode::FirstOrderOnly, 'This coupon applies to first orders only.');
        }
    }

    protected function assertUserLimit(Coupon $coupon, User $user): void
    {
        if ($coupon->per_user_limit !== null && $this->userUsageCount($coupon, $user) >= (int) $coupon->per_user_limit) {
            throw new CouponException(CouponErrorCode::UserLimitReached, 'You have already used this coupon to its limit.');
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function restrictItems(Coupon $coupon, array $items): array
    {
        $productIds = $coupon->products()->pluck('products.id');
        $categoryIds = $coupon->categories()->pluck('categories.id');
        $brandIds = $coupon->brands()->pluck('brands.id');
        $vendorIds = $this->vendorIdsFor($coupon);

        return array_values(array_filter($items, function (array $line) use ($productIds, $categoryIds, $brandIds, $vendorIds): bool {
            if ($productIds->isNotEmpty() && ! $productIds->contains($line['product_id'] ?? null)) {
                return false;
            }

            if ($categoryIds->isNotEmpty() && ! $categoryIds->contains($line['category_id'] ?? null)) {
                return false;
            }

            if ($brandIds->isNotEmpty() && ! $brandIds->contains($line['brand_id'] ?? null)) {
                return false;
            }

            return $vendorIds === null || $vendorIds->contains($line['vendor_id'] ?? null);
        }));
    }

    /**
     * @return Collection<int, int>|null
     */
    protected function vendorIdsFor(Coupon $coupon)
    {
        if ($coupon->vendors()->exists()) {
            return $coupon->vendors()->pluck('vendor_profiles.id');
        }

        if ($coupon->vendor_id !== null) {
            return collect([(int) $coupon->vendor_id]);
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function throwNotApplicable(Coupon $coupon, array $items): void
    {
        $productIds = $coupon->products()->pluck('products.id');
        $categoryIds = $coupon->categories()->pluck('categories.id');
        $brandIds = $coupon->brands()->pluck('brands.id');
        $vendorIds = $this->vendorIdsFor($coupon);

        if ($vendorIds !== null && ! $this->anyMatch($items, 'vendor_id', $vendorIds)) {
            throw new CouponException(CouponErrorCode::VendorNotApplicable, 'This coupon does not apply to any vendor in your cart.');
        }

        if ($categoryIds->isNotEmpty() && ! $this->anyMatch($items, 'category_id', $categoryIds)) {
            throw new CouponException(CouponErrorCode::CategoryNotApplicable, 'This coupon does not apply to any category in your cart.');
        }

        if ($brandIds->isNotEmpty() && ! $this->anyMatch($items, 'brand_id', $brandIds)) {
            throw new CouponException(CouponErrorCode::BrandNotApplicable, 'This coupon does not apply to any brand in your cart.');
        }

        if ($productIds->isNotEmpty() && ! $this->anyMatch($items, 'product_id', $productIds)) {
            throw new CouponException(CouponErrorCode::ProductNotApplicable, 'This coupon does not apply to any product in your cart.');
        }

        throw new CouponException(CouponErrorCode::ProductNotApplicable, 'This coupon does not apply to any product in your cart.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function anyMatch(array $items, string $key, $needles): bool
    {
        return collect($items)->pluck($key)->contains(fn ($value) => $needles->contains($value));
    }
}
