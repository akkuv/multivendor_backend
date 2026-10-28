<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhook extends Model
{
    protected $fillable = [
        'event',
        'gateway',
        'gateway_order_id',
        'gateway_payment_id',
        'payload',
        'signature',
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
