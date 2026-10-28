<?php

namespace App\Models;

use App\Enums\VendorCommissionStatus;
use Database\Factories\VendorCommissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorCommission extends Model
{
    /** @use HasFactory<VendorCommissionFactory> */
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'order_id',
        'vendor_order_id',
        'eligible_amount',
        'discount_amount',
        'commission_rate',
        'commission_amount',
        'net_payable',
        'status',
        'return_window_ends_at',
        'settled_at',
        'reversed_at',
        'reversal_reason',
    ];

    protected $casts = [
        'eligible_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'net_payable' => 'decimal:2',
        'status' => VendorCommissionStatus::class,
        'return_window_ends_at' => 'datetime',
        'settled_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendorOrder(): BelongsTo
    {
        return $this->belongsTo(VendorOrder::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CommissionTransaction::class);
    }
}
