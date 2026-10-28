<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert the legacy flat footer.social map
     * ({ facebook: url, instagram: url, ... }) into the structured
     * shape ({ enabled, title, description, style, links: [...] }).
     */
    public function up(): void
    {
        $legacyPlatforms = [
            'facebook', 'instagram', 'twitter', 'youtube', 'linkedin', 'whatsapp',
        ];

        foreach (DB::table('website_settings')->cursor() as $row) {
            $footer = is_string($row->footer) ? json_decode($row->footer, true) : $row->footer;

            if (! is_array($footer) || ! isset($footer['social']) || ! is_array($footer['social'])) {
                continue;
            }

            $social = $footer['social'];

            // Already converted (or elsewhere managed) - leave untouched.
            if (array_key_exists('links', $social)) {
                continue;
            }

            $links = [];
            foreach ($legacyPlatforms as $platform) {
                $url = trim((string) ($social[$platform] ?? ''));

                if ($url === '') {
                    continue;
                }

                $links[] = [
                    'platform' => $platform,
                    'url' => $url,
                    'label' => '',
                    'enabled' => true,
                ];
            }

            $footer['social'] = [
                'enabled' => true,
                'title' => 'Follow us',
                'description' => '',
                'style' => 'icons',
                'links' => $links,
            ];

            DB::table('website_settings')->where('id', $row->id)->update([
                'footer' => json_encode($footer),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('website_settings')->cursor() as $row) {
            $footer = is_string($row->footer) ? json_decode($row->footer, true) : $row->footer;

            if (! is_array($footer) || ! isset($footer['social']) || ! is_array($footer['social'])) {
                continue;
            }

            $social = $footer['social'];

            if (! isset($social['links']) || ! is_array($social['links'])) {
                continue;
            }

            $flat = ['facebook' => null, 'instagram' => null, 'twitter' => null, 'youtube' => null, 'linkedin' => null, 'whatsapp' => null];

            foreach ($social['links'] as $link) {
                if (! is_array($link) || ! isset($link['platform'], $link['url'])) {
                    continue;
                }

                $flat[$link['platform']] = $link['url'];
            }

            $footer['social'] = $flat;

            DB::table('website_settings')->where('id', $row->id)->update([
                'footer' => json_encode($footer),
                'updated_at' => now(),
            ]);
        }
    }
};
