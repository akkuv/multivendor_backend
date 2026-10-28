<?php

namespace App\Models;

use App\Enums\HomepageSectionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'custom_page_id',
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
        'is_active' => 'boolean',
    ];

    public function customPage(): BelongsTo
    {
        return $this->belongsTo(CustomPage::class);
    }
}
