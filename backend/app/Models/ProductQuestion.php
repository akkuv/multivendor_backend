<?php

namespace App\Models;

use Database\Factories\ProductQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductQuestion extends Model
{
    /** @use HasFactory<ProductQuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'question',
        'is_answered',
        'is_hidden',
        'reported_at',
        'report_reason',
    ];

    protected $casts = [
        'is_answered' => 'boolean',
        'is_hidden' => 'boolean',
        'reported_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ProductAnswer::class);
    }
}
