<?php

namespace App\Models;

use Database\Factories\CategoryPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CategoryPage extends Model
{
    /** @use HasFactory<CategoryPageFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'slug',
        'title',
        'subtitle',
        'description',
        'meta_title',
        'meta_description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (CategoryPage $page): void {
            if (empty($page->slug) && $page->category !== null) {
                $page->slug = Str::slug($page->category->name);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CategoryPageSection::class)->orderBy('sort_order')->orderBy('id');
    }
}
