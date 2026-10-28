<?php

namespace App\Models;

use Database\Factories\ProductAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAnswer extends Model
{
    /** @use HasFactory<ProductAnswerFactory> */
    use HasFactory;

    protected $fillable = [
        'product_question_id',
        'vendor_id',
        'answer',
        'is_hidden',
    ];

    protected $casts = [
        'is_hidden' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(ProductQuestion::class, 'product_question_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }
}
