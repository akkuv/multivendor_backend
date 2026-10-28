<?php

namespace App\Models;

use Database\Factories\ProductVideoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVideo extends Model
{
    /** @use HasFactory<ProductVideoFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'url',
        'provider',
        'thumbnail_url',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
