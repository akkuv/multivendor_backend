<?php

namespace App\Models;

use Database\Factories\WishlistNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistNotification extends Model
{
    /** @use HasFactory<WishlistNotificationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wishlist_item_id',
        'product_id',
        'type',
        'old_price',
        'new_price',
        'is_read',
    ];

    protected $casts = [
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'is_read' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wishlistItem(): BelongsTo
    {
        return $this->belongsTo(WishlistItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function priceDrop(): string
    {
        return 'price_drop';
    }

    public static function backInStock(): string
    {
        return 'back_in_stock';
    }
}
