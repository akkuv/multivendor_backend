<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'vendor_id',
        'order_item_id',
        'rating',
        'title',
        'body',
        'images',
        'is_approved',
        'is_verified',
        'is_hidden',
        'reported_at',
        'report_reason',
    ];

    protected $casts = [
        'rating' => 'integer',
        'images' => 'array',
        'is_approved' => 'boolean',
        'is_verified' => 'boolean',
        'is_hidden' => 'boolean',
        'reported_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
