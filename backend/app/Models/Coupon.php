<?php

namespace App\Models;

use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'code',
        'type',
        'value',
        'min_amount',
        'max_discount',
        'starts_at',
        'expires_at',
        'usage_limit',
        'per_user_limit',
        'first_order_only',
        'used_count',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'usage_limit' => 'integer',
        'per_user_limit' => 'integer',
        'first_order_only' => 'boolean',
        'used_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_product');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_category');
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'coupon_brand');
    }

    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(VendorProfile::class, 'coupon_vendor', 'coupon_id', 'vendor_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }
}
