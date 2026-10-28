<?php

namespace App\Services\Settings;

use App\Models\GeneralSetting;
use Illuminate\Support\Facades\Cache;

class GeneralSettingsService
{
    protected const CACHE_KEY = 'general_settings';

    public function settings(): GeneralSetting
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached instanceof GeneralSetting) {
            return $cached;
        }

        Cache::forget(self::CACHE_KEY);

        $settings = GeneralSetting::query()->firstOrCreate(['id' => 1]);
        Cache::put(self::CACHE_KEY, $settings, now()->addMinutes(5));

        return $settings;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function publicOptions(): array
    {
        $settings = $this->settings();

        return [
            'site_name' => $settings->site_name,
            'currency' => $settings->currency,
            'timezone' => $settings->timezone,
        ];
    }
}
