<?php

namespace App\Models;

use App\Enums\WalletHoldStatus;
use Database\Factories\WalletHoldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletHold extends Model
{
    /** @use HasFactory<WalletHoldFactory> */
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'user_id',
        'amount',
        'status',
        'reference_type',
        'reference_id',
        'description',
        'expires_at',
        'captured_at',
        'released_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => WalletHoldStatus::class,
        'expires_at' => 'datetime',
        'captured_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
