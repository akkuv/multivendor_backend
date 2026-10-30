<?php

namespace App\Models;

use Database\Factories\WishlistItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WishlistItem extends Model
{
    /** @use HasFactory<WishlistItemFactory> */
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'product_id',
        'price_at_add',
        'status',
        'removed_at',
        'notify_price_drops',
        'notify_back_in_stock',
        'price_drop_notified',
        'back_in_stock_notified',
    ];

    protected $casts = [
        'price_at_add' => 'decimal:2',
        'status' => 'string',
        'removed_at' => 'datetime',
        'notify_price_drops' => 'boolean',
        'notify_back_in_stock' => 'boolean',
        'price_drop_notified' => 'boolean',
        'back_in_stock_notified' => 'boolean',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REMOVED = 'removed';

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeRemoved($query)
    {
        return $query->where('status', self::STATUS_REMOVED);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(WishlistNotification::class);
    }
}
