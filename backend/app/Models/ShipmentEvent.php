<?php

namespace App\Models;

use Database\Factories\ShipmentEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentEvent extends Model
{
    /** @use HasFactory<ShipmentEventFactory> */
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'status',
        'location',
        'description',
        'is_webhook',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'is_webhook' => 'boolean',
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
