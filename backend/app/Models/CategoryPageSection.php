<?php

namespace App\Models;

use App\Enums\HomepageSectionType;
use Database\Factories\CategoryPageSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryPageSection extends Model
{
    /** @use HasFactory<CategoryPageSectionFactory> */
    use HasFactory;

    protected $fillable = [
        'category_page_id',
        'type',
        'title',
        'subtitle',
        'config',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'type' => HomepageSectionType::class,
        'config' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function categoryPage(): BelongsTo
    {
        return $this->belongsTo(CategoryPage::class);
    }
}
