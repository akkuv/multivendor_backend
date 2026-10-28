<?php

namespace App\Models;

use App\Enums\BannerPosition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'image',
        'mobile_image',
        'button_text',
        'url',
        'start_date',
        'end_date',
        'position',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'position' => BannerPosition::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];
}
