<?php

namespace App\Models;

use App\Enums\ShipmentPickupStatus;
use App\Enums\ShipmentStatus;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'vendor_order_id',
        'provider',
        'shiprocket_order_id',
        'shipment_id',
        'tracking_number',
        'courier_id',
        'courier_name',
        'tracking_url',
        'pickup_location',
        'pickup_status',
        'status',
        'last_event',
        'external_status',
        'charges',
        'weight',
        'picker',
        'payload',
        'label_url',
        'manifest_url',
        'error_message',
        'retry_count',
        'shipped_at',
        'pickup_requested_at',
        'delivered_at',
    ];

    protected $casts = [
        'charges' => 'decimal:2',
        'weight' => 'decimal:2',
        'payload' => 'array',
        'retry_count' => 'integer',
        'status' => ShipmentStatus::class,
        'pickup_status' => ShipmentPickupStatus::class,
        'shipped_at' => 'datetime',
        'pickup_requested_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendorOrder(): BelongsTo
    {
        return $this->belongsTo(VendorOrder::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('occurred_at');
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(ShipmentWebhook::class);
    }
}
