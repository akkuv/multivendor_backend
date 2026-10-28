<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralSetting extends Model
{
    protected $fillable = [
        'site_name',
        'tagline',
        'logo',
        'favicon',
        'contact_email',
        'contact_phone',
        'address',
        'currency',
        'timezone',
    ];
}
