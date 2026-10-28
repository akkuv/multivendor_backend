<?php

namespace App\Models;

use Database\Factories\SizeGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SizeGroup extends Model
{
    /** @use HasFactory<SizeGroupFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (SizeGroup $sizeGroup): void {
            if (empty($sizeGroup->slug)) {
                $sizeGroup->slug = Str::slug($sizeGroup->name);
            }
        });
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(Size::class)->orderBy('sort_order');
    }
}
