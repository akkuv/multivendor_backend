<?php

namespace App\Models;

use App\Enums\VendorPayoutStatus;
use Database\Factories\VendorPayoutFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPayout extends Model
{
    /** @use HasFactory<VendorPayoutFactory> */
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'amount',
        'method',
        'account_details',
        'status',
        'request_note',
        'admin_note',
        'requested_at',
        'approved_at',
        'paid_at',
        'rejected_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'account_details' => 'array',
        'status' => VendorPayoutStatus::class,
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }
}
