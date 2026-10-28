<?php

namespace App\Models;

use App\Enums\CommissionTransactionType;
use Database\Factories\CommissionTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionTransaction extends Model
{
    /** @use HasFactory<CommissionTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'order_id',
        'vendor_order_id',
        'vendor_commission_id',
        'rate',
        'amount',
        'type',
        'description',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'type' => CommissionTransactionType::class,
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }
}
