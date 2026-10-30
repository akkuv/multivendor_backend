<?php

namespace App\Models;

use Database\Factories\WishlistSuggestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistSuggestion extends Model
{
    /** @use HasFactory<WishlistSuggestionFactory> */
    use HasFactory;

    protected $fillable = [
        'wishlist_item_id',
        'customer_user_id',
        'product_id',
        'admin_user_id',
        'message',
        'channel',
        'status',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function wishlistItem(): BelongsTo
    {
        return $this->belongsTo(WishlistItem::class);
    }
}
