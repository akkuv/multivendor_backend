<?php

namespace App\Services\Payment;

use App\Enums\PaymentMethod;
use App\Models\PaymentSetting;
use Illuminate\Support\Facades\Cache;

class PaymentSettingsService
{
    protected const CACHE_KEY = 'payment_settings';

    public function settings(): PaymentSetting
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached instanceof PaymentSetting) {
            return $cached;
        }

        Cache::forget(self::CACHE_KEY);

        $settings = PaymentSetting::query()->firstOrCreate(['id' => 1]);
        Cache::put(self::CACHE_KEY, $settings, now()->addMinutes(5));

        return $settings;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function isMethodEnabled(string $method): bool
    {
        return match ($method) {
            PaymentMethod::Wallet->value => $this->settings()->wallet_enabled,
            PaymentMethod::Razorpay->value => $this->settings()->razorpay_enabled,
            PaymentMethod::Cod->value => $this->settings()->cod_enabled,
            default => false,
        };
    }

    public function enabledMethods(): array
    {
        $cases = [PaymentMethod::Wallet, PaymentMethod::Razorpay, PaymentMethod::Cod];

        return array_values(array_filter($cases, fn (PaymentMethod $method): bool => $this->isMethodEnabled($method->value)));
    }

    public function codFee(): float
    {
        $fee = (float) $this->settings()->cod_fee;

        if ($fee > 0) {
            return $fee;
        }

        return (float) config('shipping.cod_charge', 0.0);
    }

    /** @return array{min: float, max: float|null} */
    public function codBounds(): array
    {
        $settings = $this->settings();

        return [
            'min' => (float) $settings->cod_min_amount,
            'max' => $settings->cod_max_amount !== null ? (float) $settings->cod_max_amount : null,
        ];
    }

    public function freeDeliveryThreshold(): ?float
    {
        $threshold = $this->settings()->free_delivery_threshold;

        return $threshold !== null ? (float) $threshold : null;
    }

    /** @return array<string, mixed> */
    public function publicOptions(): array
    {
        $bounds = $this->codBounds();

        return [
            'currency' => $this->settings()->currency,
            'methods' => array_map(
                fn (PaymentMethod $method): array => [
                    'value' => $method->value,
                    'label' => $method->label(),
                    'enabled' => true,
                ],
                $this->enabledMethods(),
            ),
            'wallet' => [
                'enabled' => $this->settings()->wallet_enabled,
            ],
            'razorpay' => [
                'enabled' => $this->settings()->razorpay_enabled,
            ],
            'cod' => [
                'enabled' => $this->settings()->cod_enabled,
                'min_amount' => $bounds['min'],
                'max_amount' => $bounds['max'],
                'fee' => $this->codFee(),
            ],
            'free_delivery' => [
                'threshold' => $this->freeDeliveryThreshold(),
            ],
        ];
    }

    /**
     * Safe representation for admin UIs. Secret credentials are reported as
     * booleans so they are never serialized to React.
     *
     * @return array<string, mixed>
     */
    public function masked(): array
    {
        $settings = $this->settings();

        return [
            'id' => $settings->id,
            'razorpay_enabled' => $settings->razorpay_enabled,
            'cod_enabled' => $settings->cod_enabled,
            'wallet_enabled' => $settings->wallet_enabled,
            'razorpay_key_id' => $settings->razorpay_key_id,
            'razorpay_key_secret_set' => $settings->hasRazorpayKeySecret(),
            'razorpay_webhook_secret_set' => $settings->hasRazorpayWebhookSecret(),
            'razorpay_mode' => $settings->razorpay_mode,
            'cod_min_amount' => $settings->cod_min_amount,
            'cod_max_amount' => $settings->cod_max_amount,
            'cod_fee' => $settings->cod_fee,
            'free_delivery_threshold' => $settings->free_delivery_threshold,
            'currency' => $settings->currency,
        ];
    }

    /**
     * Persist admin-provided settings. Blank masked secrets are never clobbered.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): PaymentSetting
    {
        $settings = $this->settings();

        foreach (['razorpay_key_secret', 'razorpay_webhook_secret'] as $secret) {
            if (array_key_exists($secret, $data) && blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        $settings->update($data);
        $this->forget();

        return $this->settings();
    }
}
