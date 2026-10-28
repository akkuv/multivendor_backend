<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OrderStatusHistory extends Model
{
    protected $fillable = [
        'historable_type',
        'historable_id',
        'from_status',
        'to_status',
        'actor_type',
        'actor_id',
        'notes',
    ];

    public function historable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
