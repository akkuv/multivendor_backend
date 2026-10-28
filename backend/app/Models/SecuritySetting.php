<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecuritySetting extends Model
{
    protected $fillable = [
        'login_attempt_limit',
        'lockout_minutes',
        'otp_expiry_minutes',
        'session_timeout_minutes',
        'remember_me_days',
        'maintenance_mode',
        'maintenance_message',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
    ];
}
