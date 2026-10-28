<?php

namespace App\Models;

use Database\Factories\VendorOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class VendorOrder extends Model
{
    /** @use HasFactory<VendorOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'vendor_id',
        'vendor_order_number',
        'subtotal',
        'discount',
        'tax',
        'fees_total',
        'shipping_charge',
        'cod_charge',
        'wallet_amount',
        'total',
        'status',
        'confirmed_at',
        'packed_at',
        'ready_to_ship_at',
        'out_for_delivery_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping_charge' => 'decimal:2',
        'cod_charge' => 'decimal:2',
        'wallet_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'packed_at' => 'datetime',
        'ready_to_ship_at' => 'datetime',
        'out_for_delivery_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function vendorOrderItems(): HasMany
    {
        return $this->hasMany(VendorOrderItem::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function statusHistories(): MorphMany
    {
        return $this->morphMany(OrderStatusHistory::class, 'historable');
    }

    public function orderAddress(): HasOne
    {
        return $this->hasOneThrough(OrderAddress::class, Order::class, 'id', 'order_id', 'order_id', 'id')
            ->where('order_addresses.type', 'shipping');
    }

    public function vendorCommission(): HasOne
    {
        return $this->hasOne(VendorCommission::class);
    }
}
