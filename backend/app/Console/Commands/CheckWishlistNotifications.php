<?php

namespace App\Console\Commands;

use App\Models\WishlistItem;
use App\Models\WishlistNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckWishlistNotifications extends Command
{
    protected $signature = 'wishlist:check-notifications';

    protected $description = 'Generate price-drop and back-in-stock notifications for opted-in wishlist items.';

    public function handle(): int
    {
        $created = 0;

        DB::transaction(function () use (&$created): void {
            $items = WishlistItem::query()
                ->with('product')
                ->where(fn ($q) => $q->where('notify_price_drops', true)->orWhere('notify_back_in_stock', true))
                ->get();

            foreach ($items as $item) {
                $product = $item->product;

                if (! $product) {
                    continue;
                }

                // Price drop: notify once when the current price is below the
                // price captured when the product was added to the wishlist.
                if ($item->notify_price_drops && ! $item->price_drop_notified && $item->price_at_add !== null) {
                    $current = $product->price;

                    if ($current < (float) $item->price_at_add) {
                        WishlistNotification::create([
                            'user_id' => $item->user_id,
                            'wishlist_item_id' => $item->id,
                            'product_id' => $product->id,
                            'type' => WishlistNotification::priceDrop(),
                            'old_price' => $item->price_at_add,
                            'new_price' => $current,
                        ]);

                        $item->forceFill(['price_drop_notified' => true])->save();
                        $created++;
                    }
                }

                // Back in stock: notify once when the product becomes
                // available again after the customer opted in.
                if ($item->notify_back_in_stock && ! $item->back_in_stock_notified && $product->availableStock() > 0) {
                    WishlistNotification::create([
                        'user_id' => $item->user_id,
                        'wishlist_item_id' => $item->id,
                        'product_id' => $product->id,
                        'type' => WishlistNotification::backInStock(),
                        'new_price' => $product->price,
                    ]);

                    $item->forceFill(['back_in_stock_notified' => true])->save();
                    $created++;
                }
            }
        });

        $this->info("Created {$created} wishlist notification(s).");

        return self::SUCCESS;
    }
}
