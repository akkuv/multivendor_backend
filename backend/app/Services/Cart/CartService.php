<?php

namespace App\Services\Cart;

use App\Enums\VariantStatus;
use App\Enums\VendorStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductFee;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CartService
{
    /** @var array<int, string> */
    protected array $messages = [];

    public function messages(): array
    {
        return $this->messages;
    }

    protected function resetMessages(): void
    {
        $this->messages = [];
    }

    public function getOrCreateForUser(User $user): Cart
    {
        $cart = $user->cart()->firstOrCreate(['user_id' => $user->id]);
        $this->loadCart($cart);

        return $cart;
    }

    public function addItem(User $user, int $productId, ?int $variantId = null, int $quantity = 1): Cart
    {
        return DB::transaction(function () use ($user, $productId, $variantId, $quantity): Cart {
            $product = Product::query()->with('vendor')->find($productId);

            if (! $product || ! $product->isLive() || ! $this->vendorIsApproved($product->vendor)) {
                throw new InvalidCartException(__('This product is no longer available.'));
            }

            $variant = $this->resolveVariant($product, $variantId);

            $available = $variant !== null
                ? $variant->availableStock()
                : $product->availableStock();

            if ($available <= 0) {
                throw new InvalidCartException($variant !== null
                    ? 'This product option is currently out of stock.'
                    : 'This product is currently out of stock.');
            }

            $cart = $this->getOrCreateForUser($user);

            $existing = $cart->items->first(fn (CartItem $item) => $item->product_id === $productId && $item->product_variant_id === $variantId);

            $newQty = $quantity + ($existing?->quantity ?? 0);

            if ($newQty > $available) {
                throw new InvalidCartException("Only {$available} unit(s) available in stock.");
            }

            $cart->items()->updateOrCreate(
                [
                    'cart_id' => $cart->id,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                ],
                [
                    'quantity' => $newQty,
                    'unit_price' => $variant?->price ?? $product->price,
                    'mrp' => $variant?->mrp ?? $product->mrp,
                ],
            );

            return $this->getOrCreateForUser($user);
        });
    }

    public function updateQuantity(User $user, int $itemId, int $quantity): Cart
    {
        return DB::transaction(function () use ($user, $itemId, $quantity): Cart {
            if ($quantity <= 0) {
                return $this->removeItem($user, $itemId);
            }

            $cart = $this->getOrCreateForUser($user);
            $this->repairItems($cart);
            $this->loadCart($cart);

            $item = $cart->items->firstWhere('id', $itemId);

            if (! $item) {
                throw new InvalidCartException(__('This item is no longer available in your cart.'));
            }

            $available = $this->availableFor($item);

            if ($quantity > $available) {
                throw new InvalidCartException("Only {$available} unit(s) available in stock.");
            }

            $item->forceFill(['quantity' => $quantity])->save();

            return $this->getOrCreateForUser($user);
        });
    }

    public function removeItem(User $user, int $itemId): Cart
    {
        $cart = $this->getOrCreateForUser($user);
        $cart->items()->where('id', $itemId)->delete();

        return $this->getOrCreateForUser($user);
    }

    public function clear(User $user): void
    {
        $cart = $this->getOrCreateForUser($user);
        $cart->items()->delete();
    }

    /**
     * Fully validates and repairs every cart line against the live catalogue.
     * Drops removed/hidden products and inactive vendors/variants, clamps
     * quantities to available stock, and refreshes the price snapshots.
     */
    public function validateAndRefresh(User $user): Cart
    {
        $this->resetMessages();

        return DB::transaction(function () use ($user): Cart {
            $cart = $this->getOrCreateForUser($user);
            $this->repairItems($cart);

            return $this->getOrCreateForUser($user);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function pricing(CartItem $item): array
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

        // Prices are tax-inclusive, so extract the tax from the line total.
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
            'available_stock' => $this->availableFor($item),
        ];
    }

    /**
     * Per-vendor delivery charge based on the vendor profile's flat rate.
     * Free shipping is granted when the vendor configures a threshold and
     * the group subtotal reaches it.
     */
    public function shippingFor(?object $vendor, float $subtotal): float
    {
        if ($vendor === null) {
            return 0.0;
        }

        $threshold = $vendor->free_shipping_threshold;

        if ($threshold !== null && $subtotal >= (float) $threshold) {
            return 0.0;
        }

        return (float) $vendor->shipping_charge;
    }

    protected function loadCart(Cart $cart): void
    {
        $cart->load([
            'items' => fn ($q) => $q
                ->with(['product.vendor', 'product.category', 'product.brand', 'product.fees', 'variant'])
                ->orderBy('id'),
        ]);
    }

    protected function vendorIsApproved(?object $vendor): bool
    {
        return $vendor !== null
            && (bool) $vendor->is_approved
            && $vendor->status === VendorStatus::Approved->value;
    }

    protected function resolveVariant(Product $product, ?int $variantId): ?ProductVariant
    {
        $query = $product->variants()->where('status', VariantStatus::Active->value);

        if ($variantId !== null) {
            $variant = $query->find($variantId);

            if (! $variant) {
                throw new InvalidCartException(__('This product option is no longer available.'));
            }

            return $variant;
        }

        $hasVariants = (clone $query)->exists();

        if (! $hasVariants) {
            return null;
        }

        $variant = (clone $query)->orderBy('price')->first();

        if (! $variant || $variant->availableStock() <= 0) {
            throw new InvalidCartException(__('This product option is currently out of stock.'));
        }

        return $variant;
    }

    protected function availableFor(CartItem $item): int
    {
        if ($item->product_variant_id) {
            return $item->variant?->availableStock() ?? 0;
        }

        return $item->product?->availableStock() ?? 0;
    }

    protected function repairItems(Cart $cart): void
    {
        foreach ($cart->getAttribute('items') as $item) {
            $product = $item->product;

            $invalid = $product === null
                || ! $product->isLive()
                || ! $this->vendorIsApproved($product->vendor)
                || ($item->product_variant_id !== null && (
                    $item->variant === null
                    || $item->variant->trashed()
                    || $item->variant->status !== VariantStatus::Active->value
                    || $item->variant->product_id !== $product->id
                ));

            if ($invalid) {
                $item->delete();
                $this->messages[] = $product
                    ? "\"{$product->name}\" is no longer available and was removed from your cart."
                    : 'An item was no longer available and was removed from your cart.';

                continue;
            }

            $available = $this->availableFor($item);

            if ($available <= 0) {
                $item->delete();
                $this->messages[] = "\"{$product->name}\" is out of stock and was removed from your cart.";

                continue;
            }

            $variant = $item->product_variant_id ? $item->variant : null;
            $unitPrice = $variant?->price ?? $product->price;
            $mrp = $variant?->mrp ?? $product->mrp;

            if ($item->quantity > $available) {
                $item->forceFill(['quantity' => $available])->save();
                $this->messages[] = "Quantity for \"{$product->name}\" was adjusted to the available stock ({$available}).";
            }

            $unitPrice = round((float) $unitPrice, 2);
            $needsPriceRefresh = (float) $item->unit_price !== $unitPrice
                || ($mrp !== null && $item->mrp === null)
                || ($mrp === null && $item->mrp !== null)
                || ($mrp !== null && $item->mrp !== null && (float) $item->mrp !== round((float) $mrp, 2));

            if ($needsPriceRefresh) {
                $item->forceFill([
                    'unit_price' => $unitPrice,
                    'mrp' => $mrp !== null ? round((float) $mrp, 2) : null,
                ])->save();
            }
        }
    }
}
