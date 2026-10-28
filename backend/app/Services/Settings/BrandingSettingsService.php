<?php

namespace App\Services\Settings;

use App\Models\BrandingSetting;
use Illuminate\Support\Facades\Cache;

class BrandingSettingsService
{
    protected const CACHE_KEY = 'branding_settings';

    public function settings(): BrandingSetting
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached instanceof BrandingSetting) {
            return $cached;
        }

        Cache::forget(self::CACHE_KEY);

        $settings = BrandingSetting::query()->firstOrCreate(['id' => 1]);
        Cache::put(self::CACHE_KEY, $settings, now()->addMinutes(5));

        return $settings;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
