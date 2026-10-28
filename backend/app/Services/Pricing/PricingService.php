<?php

namespace App\Services\Pricing;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductFee;
use App\Services\Cart\CartService;
use App\Services\Coupon\CouponService;
use App\Services\Payment\PaymentSettingsService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Collection;

class PricingService
{
    public function __construct(
        protected CartService $cartService,
        protected CouponService $couponService,
        protected WalletService $walletService,
        protected PaymentSettingsService $paymentSettings,
    ) {}

    /**
     * Compute the full price breakdown for a user's cart. This is the single
     * source of truth for order totals; clients must never derive final
     * payable amounts themselves.
     *
     * @return array<string, mixed>
     */
    public function breakdown(Cart $cart, ?string $couponCode = null, string $paymentMethod = 'razorpay', ?float $walletAmount = null): array
    {
        $lines = $cart->items->map(fn ($item) => $this->line($item))->values()->all();

        $vendorGroups = $this->emptyVendorGroups($lines);

        $subtotal = round((float) array_sum(array_column($lines, 'subtotal')), 2);
        $lineDiscount = round((float) array_sum(array_column($lines, 'discount')), 2);
        $tax = round((float) array_sum(array_column($lines, 'tax')), 2);
        $feesTotal = round((float) array_sum(array_column($lines, 'fees_total')), 2);

        $freeDeliveryThreshold = $this->paymentSettings->freeDeliveryThreshold();
        if ($freeDeliveryThreshold !== null && $subtotal >= $freeDeliveryThreshold) {
            $vendorGroups->transform(fn (array $group): array => [...$group, 'shipping' => 0.0]);
        }

        $shipping = round($vendorGroups->sum('shipping'), 2);

        $couponDiscount = 0.0;
        $coupon = null;
        if ($couponCode) {
            $result = $this->couponService->apply($couponCode, $lines, $cart->user);
            $coupon = $result['coupon'];
            $couponDiscount = $result['discount'];
            $this->allocateCoupon($vendorGroups, $result['eligible_items'], $couponDiscount);
        }

        $codCharge = $paymentMethod === 'cod' ? $this->paymentSettings->codFee() : 0.0;
        if ($codCharge > 0) {
            $this->allocateProportionally($vendorGroups, 'cod_charge', $codCharge, 'subtotal');
        }

        // Tax stays inside the subtotal (inclusive pricing), so it is never
        // added on top when the payable amount is derived. Add-on fees are a
        // non-taxable surcharge on top of the subtotal.
        $grandTotal = max(0.0, round($subtotal + $feesTotal + $shipping + $codCharge - $couponDiscount, 2));

        $walletUsed = $this->resolveWalletAmount($cart, $walletAmount, $paymentMethod, $grandTotal);
        if ($walletUsed > 0) {
            $this->allocateByWeights($vendorGroups, 'wallet_amount', $walletUsed, fn (array $group): float => max(
                0.0,
                (float) $group['subtotal'] + (float) $group['fees_total'] + (float) $group['shipping'] + (float) $group['cod_charge'] - (float) $group['coupon_discount'],
            ));
        }

        $vendorGroups->transform(function (array $group): array {
            $group['total'] = max(0.0, round(
                (float) $group['subtotal'] + (float) $group['fees_total'] + (float) $group['shipping'] + (float) $group['cod_charge'] - (float) $group['coupon_discount'] - (float) $group['wallet_amount'],
                2,
            ));

            return $group;
        });

        return [
            'items' => $lines,
            'coupon' => $coupon,
            'vendor_groups' => array_values($vendorGroups->all()),
            'subtotal' => $subtotal,
            'discount' => $lineDiscount,
            'coupon_discount' => $couponDiscount,
            'tax' => $tax,
            'fees_total' => $feesTotal,
            'shipping' => $shipping,
            'cod_charge' => round($codCharge, 2),
            'wallet_amount' => round($walletUsed, 2),
            'grand_total' => $grandTotal,
            'payable' => round((float) $vendorGroups->sum('total'), 2),
            'savings' => round($lineDiscount + $couponDiscount, 2),
        ];
    }

    /**
     * Price a single cart line deterministically. Shared by the cart payloads
     * and order creation so the numbers always match.
     *
     * @return array<string, mixed>
     */
    public function line(CartItem $item): array
    {
        $product = $item->product;
        $variant = $item->product_variant_id ? $item->variant : null;

        $unitPrice = round((float) ($variant?->price ?? $product?->price ?? $item->unit_price), 2);
        $mrpValue = $variant?->mrp ?? $product?->mrp;
        $mrp = $mrpValue !== null ? round((float) $mrpValue, 2) : null;

        $quantity = max((int) $item->quantity, 0);
        $taxRate = (float) ($product?->gst ?? 0);

        $unitDiscount = ($mrp !== null && $mrp > $unitPrice) ? round($mrp - $unitPrice, 2) : 0.0;
        $lineTotal = round($unitPrice * $quantity, 2);
        $lineDiscount = round($unitDiscount * $quantity, 2);

        // The unit price is tax-inclusive: the line's tax is extracted back
        // out of the total (price × rate ÷ (100 + rate)) rather than added.
        $lineTax = $taxRate > 0 ? round($lineTotal * ($taxRate / (100 + $taxRate)), 2) : 0.0;

        // Add-on fees (handling charge, packaging…) are non-taxable surcharges
        // charged once per cart line, regardless of quantity.
        $fees = $product?->fees
            ?->map(fn (ProductFee $fee): array => [
                'name' => (string) $fee->name,
                'amount' => round((float) $fee->amount, 2),
            ])
            ->values()
            ->all() ?? [];
        $feesTotal = round((float) array_sum(array_column($fees, 'amount')), 2);

        return [
            'cart_item_id' => (int) $item->id,
            'product_id' => (int) $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'vendor_id' => (int) ($product?->vendor_id ?? 0),
            'vendor' => $product?->vendor,
            'category_id' => $product?->category_id,
            'category' => $product?->category,
            'brand_id' => $product?->brand_id,
            'brand' => $product?->brand,
            'product_name' => $product?->name ?? 'Product',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'mrp' => $mrp,
            'unit_discount' => $unitDiscount,
            'discount' => $lineDiscount,
            'tax' => $lineTax,
            'tax_rate' => $taxRate,
            'subtotal' => $lineTotal,
            'fees' => $fees,
            'fees_total' => $feesTotal,
            'available_stock' => $this->cartService->pricing($item)['available_stock'],
        ];
    }

    /**
     * Build the per-vendor group skeleton from cart lines.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return Collection<int, array>
     */
    protected function emptyVendorGroups(array $lines): Collection
    {
        $groups = collect($lines)->groupBy(fn (array $line) => $line['vendor_id']);

        return $groups->map(function (Collection $groupLines): array {
            $vendorId = (int) $groupLines->first()['vendor_id'];

            return [
                'vendor_id' => $vendorId,
                'vendor' => $groupLines->first()['vendor'] ?? null,
                'subtotal' => round((float) $groupLines->sum('subtotal'), 2),
                'discount' => round((float) $groupLines->sum('discount'), 2),
                'tax' => round((float) $groupLines->sum('tax'), 2),
                'fees_total' => round((float) $groupLines->sum('fees_total'), 2),
                'shipping' => round($this->cartService->shippingFor($groupLines->first()['vendor'] ?? null, (float) $groupLines->sum('subtotal')), 2),
                'cod_charge' => 0.0,
                'coupon_discount' => 0.0,
                'wallet_amount' => 0.0,
                'total' => 0.0,
            ];
        });
    }

    /**
     * Distribute the coupon discount across vendor groups, weighted by each
     * group's eligible subtotal so restricted coupons never leak a discount
     * onto ineligible vendors.
     *
     * @param  Collection<int, array>  $groups
     * @param  array<int, array<string, mixed>>  $eligibleItems
     */
    protected function allocateCoupon(Collection &$groups, array $eligibleItems, float $couponDiscount): void
    {
        $eligibleByVendor = collect($eligibleItems)
            ->groupBy('vendor_id')
            ->map->sum('subtotal');

        $this->allocateByWeights(
            $groups,
            'coupon_discount',
            $couponDiscount,
            fn (array $group): float => (float) ($eligibleByVendor[$group['vendor_id']] ?? 0),
        );
    }

    /**
     * @param  Collection<int, array>  $groups
     */
    protected function allocateProportionally(Collection &$groups, string $targetKey, float $amount, string $weightKey): void
    {
        $this->allocateByWeights($groups, $targetKey, $amount, fn (array $group): float => (float) $group[$weightKey]);
    }

    /**
     * Split an amount across vendor groups by a weight resolver. The last
     * group absorbs any rounding remainder so shares always sum exactly.
     *
     * @param  Collection<int, array>  $groups
     */
    protected function allocateByWeights(Collection &$groups, string $targetKey, float $amount, callable $weightResolver): void
    {
        $rows = $groups->all();
        $weights = $groups->map($weightResolver)->all();
        $weightTotal = array_sum($weights);

        if ($amount <= 0 || $weightTotal <= 0) {
            return;
        }

        $allocated = 0.0;
        $keys = array_keys($rows);
        $lastKey = end($keys);

        foreach ($keys as $key) {
            $share = $key === $lastKey
                ? round($amount - $allocated, 2)
                : round($amount * ((float) $weights[$key] / $weightTotal), 2);

            $allocated += $share;
            $rows[$key][$targetKey] = $share;
        }

        $groups = collect($rows);
    }

    protected function resolveWalletAmount(Cart $cart, ?float $walletAmount, string $paymentMethod, float $grandTotal): float
    {
        if ($paymentMethod === 'wallet' || ($walletAmount !== null && $walletAmount > 0)) {
            $balance = $this->walletService->balance($cart->user);
            $requested = $paymentMethod === 'wallet' ? $balance : (float) $walletAmount;

            return round(min($requested, $balance, $grandTotal), 2);
        }

        return 0.0;
    }
}
