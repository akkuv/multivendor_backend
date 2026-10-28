<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentWebhook extends Model
{
    protected $fillable = [
        'shipment_id',
        'awb',
        'event',
        'payload',
        'source_ip',
        'verified',
        'status',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'verified' => 'boolean',
        'processed_at' => 'datetime',
    ];
}
