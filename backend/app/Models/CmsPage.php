<?php

namespace App\Models;

use App\Enums\CmsPageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'slug',
        'title',
        'icon',
        'content',
        'meta_title',
        'meta_description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'type' => CmsPageType::class,
        'is_active' => 'boolean',
    ];
}
