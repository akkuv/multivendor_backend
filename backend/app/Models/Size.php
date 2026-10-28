<?php

namespace App\Models;

use Database\Factories\SizeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Size extends Model
{
    /** @use HasFactory<SizeFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'size_group_id',
        'name',
        'slug',
        'sort_order',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Size $size): void {
            if (empty($size->slug)) {
                $size->slug = Str::slug($size->name);
            }
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SizeGroup::class, 'size_group_id');
    }
}
