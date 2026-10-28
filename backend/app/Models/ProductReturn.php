<?php

namespace App\Models;

use Database\Factories\ProductReturnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductReturn extends Model
{
    /** @use HasFactory<ProductReturnFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'user_id',
        'quantity',
        'reason',
        'description',
        'images',
        'status',
        'status_notes',
        'pickup_address_id',
        'pickup_scheduled_at',
        'picked_up_at',
        'received_at',
        'inspecting_at',
        'approved_for_refund_at',
        'refunded_at',
        'closed_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'images' => 'array',
        'pickup_scheduled_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'received_at' => 'datetime',
        'inspecting_at' => 'datetime',
        'approved_for_refund_at' => 'datetime',
        'refunded_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function pickupAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'pickup_address_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
