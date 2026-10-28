<?php

namespace App\Models;

use Database\Factories\CommissionSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionSetting extends Model
{
    /** @use HasFactory<CommissionSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'rate',
        'type',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
