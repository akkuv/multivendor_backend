<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminFinancialLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'description',
        'amount',
        'order_id',
        'refund_id',
        'commission_id',
        'vendor_id',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
