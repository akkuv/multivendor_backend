<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandingSetting extends Model
{
    protected $fillable = [
        'logo',
        'dark_logo',
        'favicon',
        'primary_color',
        'secondary_color',
        'accent_color',
        'facebook',
        'instagram',
        'twitter',
        'youtube',
        'linkedin',
        'whatsapp',
    ];
}
