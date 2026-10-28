<?php

namespace App\Models;

use App\Enums\CommissionLevel;
use App\Enums\CommissionRuleType;
use Database\Factories\CommissionRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model
{
    /** @use HasFactory<CommissionRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'level',
        'scope_id',
        'name',
        'rate',
        'type',
        'is_active',
    ];

    protected $casts = [
        'level' => CommissionLevel::class,
        'rate' => 'decimal:2',
        'type' => CommissionRuleType::class,
        'is_active' => 'boolean',
    ];

    public function applyTo(float $amount): float
    {
        if ($this->type === CommissionRuleType::Fixed) {
            return round((float) $this->rate, 2);
        }

        return round($amount * ((float) $this->rate / 100), 2);
    }
}
