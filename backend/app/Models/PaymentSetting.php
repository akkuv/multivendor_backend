<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    protected $fillable = [
        'razorpay_enabled',
        'cod_enabled',
        'wallet_enabled',
        'razorpay_key_id',
        'razorpay_key_secret',
        'razorpay_webhook_secret',
        'razorpay_mode',
        'cod_min_amount',
        'cod_max_amount',
        'cod_fee',
        'free_delivery_threshold',
        'currency',
    ];

    protected $casts = [
        'razorpay_enabled' => 'boolean',
        'cod_enabled' => 'boolean',
        'wallet_enabled' => 'boolean',
        'razorpay_key_secret' => 'encrypted',
        'razorpay_webhook_secret' => 'encrypted',
        'cod_min_amount' => 'decimal:2',
        'cod_max_amount' => 'decimal:2',
        'cod_fee' => 'decimal:2',
        'free_delivery_threshold' => 'decimal:2',
    ];

    public function hasRazorpayKeySecret(): bool
    {
        return filled($this->razorpay_key_secret);
    }

    public function hasRazorpayWebhookSecret(): bool
    {
        return filled($this->razorpay_webhook_secret);
    }
}
