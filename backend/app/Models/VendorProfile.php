<?php

namespace App\Models;

use Database\Factories\VendorProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VendorProfile extends Model
{
    /** @use HasFactory<VendorProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_name',
        'slug',
        'description',
        'logo',
        'banner',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'country',
        'pincode',
        'gstin',
        'pan',
        'bank_account_name',
        'bank_account_number',
        'bank_ifsc',
        'bank_name',
        'upi_id',
        'business_documents',
        'commission_rate',
        'shipping_charge',
        'free_shipping_threshold',
        'is_approved',
        'status',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'commission_rate' => 'decimal:2',
        'shipping_charge' => 'decimal:2',
        'free_shipping_threshold' => 'decimal:2',
        'business_documents' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'vendor_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(VendorOrder::class, 'vendor_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(VendorWallet::class, 'vendor_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(VendorCommission::class, 'vendor_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(VendorPayout::class, 'vendor_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(VendorReview::class, 'vendor_id');
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()
            ->where('is_approved', true)
            ->where('is_hidden', false);
    }

    public function averageRating(): ?float
    {
        $avg = $this->approvedReviews()->avg('rating');

        return $avg === null ? null : round((float) $avg, 1);
    }

    public function reviewCount(): int
    {
        return $this->approvedReviews()->count();
    }
}
