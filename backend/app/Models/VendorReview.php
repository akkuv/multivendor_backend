<?php

namespace App\Models;

use App\Enums\VendorOrderStatus;
use Database\Factories\VendorReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorReview extends Model
{
    /** @use HasFactory<VendorReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'user_id',
        'order_id',
        'rating',
        'title',
        'body',
        'is_verified',
        'is_approved',
        'is_hidden',
        'reported_at',
        'report_reason',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_verified' => 'boolean',
        'is_approved' => 'boolean',
        'is_hidden' => 'boolean',
        'reported_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Whether the customer resolved a delivered/completed order with this
     * vendor, making them eligible to leave a verified vendor review.
     */
    public static function customerCompletedOrder(VendorProfile $vendor, User $user): bool
    {
        return VendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->whereIn('status', [
                VendorOrderStatus::Delivered->value,
                VendorOrderStatus::Returned->value,
                VendorOrderStatus::Refunded->value,
            ])
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }
}
