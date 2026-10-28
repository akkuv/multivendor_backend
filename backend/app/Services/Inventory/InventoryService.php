<?php

namespace App\Services\Inventory;

use App\Enums\InventoryTransactionType;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Record an inventory change under a database transaction with row locking.
     *
     * Positive quantities add to available stock; decrementing types
     * (RESERVE, SOLD, DAMAGED) are never allowed to push available stock
     * below zero.
     */
    public function adjust(
        Product $product,
        ?ProductVariant $variant,
        string $type,
        int $quantity,
        ?string $reason = null,
        ?object $reference = null,
        ?int $createdBy = null,
    ): InventoryTransaction {
        $this->assertValidType($type);

        return DB::transaction(function () use ($product, $variant, $type, $quantity, $reason, $reference, $createdBy): InventoryTransaction {
            $target = $this->lockTarget($product, $variant);

            $stockColumn = $target instanceof ProductVariant ? 'variant' : 'product';
            $stock = (int) $target->stock;
            $reserved = (int) $target->reserved_stock;
            $available = $stock - $reserved;

            switch ($type) {
                case InventoryTransactionType::Reserve->value:
                    $this->assertSufficient($available, $quantity);
                    $target->increment('reserved_stock', $quantity);
                    $delta = -$quantity;

                    break;

                case InventoryTransactionType::Release->value:
                    $released = min($quantity, $reserved);
                    $target->decrement('reserved_stock', $released);
                    $delta = $released;

                    break;

                case InventoryTransactionType::Sold->value:
                case InventoryTransactionType::Damaged->value:
                    $this->assertSufficient($available, $quantity);
                    $fromReserved = min($quantity, $reserved);
                    if ($fromReserved > 0) {
                        $target->decrement('reserved_stock', $fromReserved);
                    }
                    $target->decrement('stock', $quantity);
                    $delta = -($quantity - $fromReserved);

                    break;

                default: // ADD, RETURN
                    $target->increment('stock', $quantity);
                    $delta = $quantity;

                    break;
            }

            $target->refresh();

            $availableAfter = (int) $target->stock - (int) $target->reserved_stock;

            return InventoryTransaction::create([
                'product_id' => $product->id,
                'product_variant_id' => $stockColumn === 'variant' ? $target->id : null,
                'type' => $type,
                'quantity' => $delta,
                'stock_before' => $available,
                'stock_after' => $availableAfter,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->id,
                'reason' => $reason,
                'created_by' => $createdBy,
            ]);
        });
    }

    public function reserve(
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        ?object $reference = null,
        ?int $createdBy = null,
    ): InventoryTransaction {
        return $this->adjust(
            $product,
            $variant,
            InventoryTransactionType::Reserve->value,
            $quantity,
            'Stock reserved for order.',
            $reference,
            $createdBy,
        );
    }

    public function release(
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        ?object $reference = null,
        ?int $createdBy = null,
    ): InventoryTransaction {
        return $this->adjust(
            $product,
            $variant,
            InventoryTransactionType::Release->value,
            $quantity,
            'Reserved stock released.',
            $reference,
            $createdBy,
        );
    }

    public function sold(
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        ?object $reference = null,
        ?int $createdBy = null,
    ): InventoryTransaction {
        return $this->adjust(
            $product,
            $variant,
            InventoryTransactionType::Sold->value,
            $quantity,
            'Stock sold.',
            $reference,
            $createdBy,
        );
    }

    private function lockTarget(Product $product, ?ProductVariant $variant): Product|ProductVariant
    {
        if ($variant) {
            return ProductVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
        }

        return Product::query()
            ->whereKey($product->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertSufficient(int $available, int $quantity): void
    {
        if ($available < $quantity) {
            throw new InsufficientStockException(
                "Insufficient stock. Only {$available} unit(s) available."
            );
        }
    }

    private function assertValidType(string $type): void
    {
        $valid = array_column(InventoryTransactionType::cases(), 'value');

        if (! in_array($type, $valid, true)) {
            throw new \InvalidArgumentException("Invalid inventory transaction type: {$type}");
        }
    }
}
