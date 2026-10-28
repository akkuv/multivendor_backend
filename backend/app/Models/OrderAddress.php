<?php

namespace App\Models;

use Database\Factories\OrderAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAddress extends Model
{
    /** @use HasFactory<OrderAddressFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'type',
        'name',
        'phone',
        'address_line1',
        'address_line2',
        'landmark',
        'city',
        'state',
        'country',
        'pincode',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
