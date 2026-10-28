<?php

namespace App\Models;

use App\Enums\VendorWalletStatus;
use Database\Factories\VendorWalletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorWallet extends Model
{
    /** @use HasFactory<VendorWalletFactory> */
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'currency',
        'available_balance',
        'pending_balance',
        'held_balance',
        'paid_total',
        'status',
    ];

    protected $casts = [
        'available_balance' => 'decimal:2',
        'pending_balance' => 'decimal:2',
        'held_balance' => 'decimal:2',
        'paid_total' => 'decimal:2',
        'status' => VendorWalletStatus::class,
    ];

    protected $attributes = [
        'currency' => 'INR',
        'status' => VendorWalletStatus::Active,
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(VendorWalletTransaction::class, 'wallet_id')->orderBy('id');
    }

    public function totalBalance(): float
    {
        return round(
            (float) $this->available_balance
            + (float) $this->pending_balance
            + (float) $this->held_balance,
            2,
        );
    }
}
