<?php

namespace App\Services\Settings;

use App\Models\SecuritySetting;
use Illuminate\Support\Facades\Cache;

class SecuritySettingsService
{
    protected const CACHE_KEY = 'security_settings';

    public function settings(): SecuritySetting
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached instanceof SecuritySetting) {
            return $cached;
        }

        Cache::forget(self::CACHE_KEY);

        $settings = SecuritySetting::query()->firstOrCreate(['id' => 1]);
        Cache::put(self::CACHE_KEY, $settings, now()->addMinutes(5));

        return $settings;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function loginAttemptLimit(): int
    {
        return (int) $this->settings()->login_attempt_limit;
    }

    public function lockoutMinutes(): int
    {
        return (int) $this->settings()->lockout_minutes;
    }

    public function otpExpiryMinutes(): int
    {
        return (int) $this->settings()->otp_expiry_minutes;
    }

    public function maintenanceMode(): bool
    {
        return (bool) $this->settings()->maintenance_mode;
    }
}
