<?php

namespace App\Services\Settings;

use App\Models\WebsiteSetting;
use Illuminate\Support\Facades\Cache;

class WebsiteSettingsService
{
    protected const CACHE_KEY = 'website_settings';

    /** Curated social platforms an admin may link to from the footer. */
    public const SOCIAL_PLATFORMS = [
        'facebook',
        'instagram',
        'twitter',
        'youtube',
        'linkedin',
        'whatsapp',
        'tiktok',
        'telegram',
        'threads',
        'website',
    ];

    public function settings(): WebsiteSetting
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached instanceof WebsiteSetting) {
            return $cached;
        }

        Cache::forget(self::CACHE_KEY);

        $settings = WebsiteSetting::query()->firstOrCreate(['id' => 1]);
        Cache::put(self::CACHE_KEY, $settings, now()->addMinutes(5));

        return $settings;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function defaultNavbar(): array
    {
        return [
            'announcement' => [
                'enabled' => false,
                'text' => '',
                'url' => null,
            ],
            'logo' => [
                'text' => 'Waplia',
                'image' => null,
                'url' => '/',
            ],
            'links' => [
                ['label' => 'Home', 'url' => '/', 'new_tab' => false],
                ['label' => 'Shop', 'url' => '/shop', 'new_tab' => false],
            ],
            'departments' => [
                'enabled' => true,
                'category_ids' => null,
                'show_all' => true,
                'show_shop_all' => true,
                'show_collections' => true,
            ],
        ];
    }

    public static function defaultFooter(): array
    {
        return [
            'about' => [
                'text' => 'Waplia is a multi-vendor marketplace connecting you with verified sellers across thousands of products.',
            ],
            'columns' => [
                [
                    'title' => 'Shop',
                    'links' => [
                        ['label' => 'All Products', 'url' => '/shop', 'new_tab' => false],
                        ['label' => 'Deals', 'url' => '/shop?sort=discount', 'new_tab' => false],
                    ],
                ],
                [
                    'title' => 'My Account',
                    'links' => [
                        ['label' => 'My Account', 'url' => '/account', 'new_tab' => false],
                        ['label' => 'Wishlist', 'url' => '/wishlist', 'new_tab' => false],
                        ['label' => 'Cart', 'url' => '/cart', 'new_tab' => false],
                    ],
                ],
            ],
            'social' => [
                'enabled' => true,
                'title' => 'Follow us',
                'description' => '',
                'style' => 'icons',
                'links' => array_map(fn (string $platform) => [
                    'platform' => $platform,
                    'url' => null,
                    'label' => '',
                    'enabled' => true,
                ], [
                    'facebook',
                    'instagram',
                    'twitter',
                    'youtube',
                    'linkedin',
                    'whatsapp',
                ]),
            ],
            'contact' => [
                'email' => null,
                'phone' => null,
                'address' => null,
            ],
            'copyright' => 'All rights reserved.',
        ];
    }

    public static function defaultMail(): array
    {
        return [
            'mail_mailer' => config('mail.default', env('MAIL_MAILER', 'smtp')),
            'mail_host' => env('MAIL_HOST', ''),
            'mail_port' => (string) env('MAIL_PORT', '587'),
            'mail_username' => env('MAIL_USERNAME', ''),
            'mail_password' => env('MAIL_PASSWORD', ''),
            'mail_encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'mail_from_address' => env('MAIL_FROM_ADDRESS', ''),
            'mail_from_name' => env('MAIL_FROM_NAME', config('app.name')),
        ];
    }

    public function navbar(): array
    {
        return self::merge(self::defaultNavbar(), $this->settings()->navbar ?? []);
    }

    public function footer(): array
    {
        return self::merge(self::defaultFooter(), $this->settings()->footer ?? []);
    }

    public function mail(): array
    {
        return self::merge(self::defaultMail(), $this->settings()->mail ?? []);
    }

    public function publicContent(): array
    {
        return [
            'navbar' => $this->navbar(),
            'footer' => $this->footer(),
            'mail' => $this->mail(),
        ];
    }

    public static function merge(array $defaults, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value)
                && isset($defaults[$key])
                && is_array($defaults[$key])
                && ! array_is_list($defaults[$key])) {
                $defaults[$key] = self::merge($defaults[$key], $value);
            } else {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }
}
