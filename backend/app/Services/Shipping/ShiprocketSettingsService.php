<?php

namespace App\Services\Shipping;

use App\Models\ShiprocketSetting;
use Illuminate\Support\Facades\Cache;

class ShiprocketSettingsService
{
    protected const CACHE_KEY = 'shiprocket_settings';

    public function settings(): ShiprocketSetting
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached instanceof ShiprocketSetting) {
            return $cached;
        }

        Cache::forget(self::CACHE_KEY);

        $settings = ShiprocketSetting::query()->firstOrCreate(['id' => 1]);
        Cache::put(self::CACHE_KEY, $settings, now()->addMinutes(5));

        return $settings;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function enabled(): bool
    {
        return $this->settings()->enabled;
    }

    public function baseUrl(): string
    {
        return $this->settings()->base_url ?: (string) config('services.shiprocket.base_url', 'https://apiv2.shiprocket.in/v1/external');
    }

    public function token(): ?string
    {
        $setting = $this->settings();

        return $setting->hasToken() ? $setting->token : config('services.shiprocket.token');
    }

    /** @return array{email: ?string, password: ?string, token: ?string} */
    public function credentials(): array
    {
        $setting = $this->settings();

        return [
            'email' => $setting->email ?: config('services.shiprocket.email'),
            'password' => $setting->hasPassword() ? $setting->password : config('services.shiprocket.password'),
            'token' => $this->token(),
        ];
    }

    public function webhookSecret(): ?string
    {
        return $this->settings()->webhook_secret;
    }

    public function defaultPickupLocation(): ?string
    {
        return $this->settings()->default_pickup_location ?: null;
    }

    /**
     * Update settings without ever overwriting a secret with an empty or
     * masked marker sent by an admin UI.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): ShiprocketSetting
    {
        $setting = $this->settings();

        foreach (['password', 'token', 'webhook_secret'] as $secret) {
            if (array_key_exists($secret, $data) && blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        $setting->update($data);
        $this->forget();

        return $this->settings();
    }

    /**
     * Safe representation for admin UIs. Secrets are reported as booleans so
     * they are never serialized back to React.
     *
     * @return array<string, mixed>
     */
    public function masked(): array
    {
        $setting = $this->settings();

        return [
            'id' => $setting->id,
            'enabled' => $setting->enabled,
            'environment' => $setting->environment,
            'base_url' => $setting->base_url,
            'email' => $setting->email,
            'password_set' => $setting->hasPassword(),
            'token_set' => $setting->hasToken(),
            'webhook_secret_set' => filled($setting->webhook_secret),
            'default_pickup_location' => $setting->default_pickup_location,
            'pickup_locations' => $setting->pickup_locations ?? [],
            'base_url_default' => (string) config('services.shiprocket.base_url'),
            'webhook_url' => route('shipments.webhook'),
        ];
    }
}
