<?php

namespace App\Models;

use App\Enums\VendorWalletTransactionStatus;
use App\Enums\VendorWalletTransactionType;
use Database\Factories\VendorWalletTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class VendorWalletTransaction extends Model
{
    /** @use HasFactory<VendorWalletTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'amount',
        'balance_before',
        'balance_after',
        'type',
        'status',
        'description',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'type' => VendorWalletTransactionType::class,
        'status' => VendorWalletTransactionStatus::class,
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(VendorWallet::class, 'wallet_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
