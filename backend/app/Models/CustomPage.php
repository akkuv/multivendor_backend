<?php

namespace App\Models;

use Database\Factories\CustomPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CustomPage extends Model
{
    /** @use HasFactory<CustomPageFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
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
        static::saving(function (CustomPage $page): void {
            if (empty($page->slug)) {
                $page->slug = static::normalizeSlug((string) $page->title);
            }
        });
    }

    /**
     * Normalize an admin-provided slug while keeping nested path segments, so a
     * page can live at any storefront path such as `/women/women-ethnic-wear`.
     */
    public static function normalizeSlug(string $slug): string
    {
        $segments = array_map(
            static fn (string $segment): string => Str::slug($segment),
            explode('/', $slug),
        );

        return implode('/', array_filter($segments, static fn (string $segment): bool => $segment !== ''));
    }

    public function sections(): HasMany
    {
        return $this->hasMany(HomepageSection::class)->orderBy('sort_order')->orderBy('id');
    }
}
