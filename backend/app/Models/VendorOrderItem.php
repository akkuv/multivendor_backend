<?php

namespace App\Models;

use Database\Factories\VendorOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorOrderItem extends Model
{
    /** @use HasFactory<VendorOrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'vendor_order_id',
        'order_item_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'product_image',
        'unit_price',
        'quantity',
        'total',
        'fees_total',
        'fees',
        'commission_rate',
        'commission_amount',
        'status',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'total' => 'decimal:2',
        'fees_total' => 'decimal:2',
        'fees' => 'array',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
    ];

    public function vendorOrder(): BelongsTo
    {
        return $this->belongsTo(VendorOrder::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
