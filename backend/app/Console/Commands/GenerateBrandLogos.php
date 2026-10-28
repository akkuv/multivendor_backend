<?php

namespace App\Console\Commands;

use App\Models\Brand;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('brands:generate-logos')]
#[Description('Generate PNG brand logo images for all brands')]
class GenerateBrandLogos extends Command
{
    private const WIDTH = 512;

    private const HEIGHT = 160;

    private const RADIUS = 28;

    private const PALETTES = [
        ['#1b1b2b', '#3f3c52'],
        ['#7c4a34', '#b96a4a'],
        ['#1e3a8a', '#0ea5e9'],
        ['#0f766e', '#14b8a6'],
        ['#ea580c', '#fbbf24'],
        ['#6d28d9', '#a78bfa'],
        ['#be123c', '#fb7185'],
        ['#0e7490', '#22d3ee'],
        ['#166534', '#4ade80'],
        ['#854d0e', '#facc15'],
        ['#334155', '#94a3b8'],
        ['#9d174d', '#f9a8d4'],
    ];

    public function handle(): int
    {
        $font = '/System/Library/Fonts/Supplemental/Arial Bold.ttf';
        if (! is_file($font)) {
            $this->error("Font not found: {$font}");

            return self::FAILURE;
        }

        $outDir = public_path('uploads/brands');
        if (! is_dir($outDir)) {
            mkdir($outDir, 0775, true);
        }

        $brands = Brand::query()->orderBy('id')->get();
        $this->info("Generating logos for {$brands->count()} brands…");

        foreach ($brands as $brand) {
            [$from, $to] = self::PALETTES[crc32((string) $brand->id) % count(self::PALETTES)];

            $image = $this->renderLogo($brand->name, $from, $to, $font);
            $path = $outDir.'/'.$brand->slug.'.png';
            imagepng($image, $path);
            imagedestroy($image);

            // Absolute so the frontend can load it straight from the API origin.
            $url = url('uploads/brands/'.$brand->slug.'.png');
            $brand->update(['logo' => $url]);

            $this->line("   <info>✓</info> {$brand->name} -> {$url}");
        }

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }

    private function renderLogo(string $name, string $from, string $to, string $font): \GdImage
    {
        if (! \extension_loaded('gd')) {
            throw new RuntimeException(__('The GD extension is required to render brand logos.'));
        }

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagesavealpha($image, true);

        $corners = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        $transparent = imagecolorallocatealpha($corners, 0, 0, 0, 127);
        imagefill($corners, 0, 0, $transparent);

        $opaque = $this->toRgba($corners, $from);
        $radius = self::RADIUS;
        imagefilledrectangle($corners, 0, $radius + 1, self::WIDTH - 1, self::HEIGHT - 1 - $radius, $opaque);
        imagefilledrectangle($corners, $radius + 1, 0, self::WIDTH - 1 - $radius, self::HEIGHT - 1, $opaque);

        foreach ([0, self::WIDTH - 1] as $x0) {
            foreach ([0, self::HEIGHT - 1] as $y0) {
                $cx = $x0 === 0 ? $radius : self::WIDTH - 1 - $radius;
                $cy = $y0 === 0 ? $radius : self::HEIGHT - 1 - $radius;
                imagefilledrectangle($corners, min($x0, $cx), min($y0, $cy), max($x0, $cx), max($y0, $cy), $opaque);
                imagefilledellipse($corners, $cx, $cy, $radius * 2, $radius * 2, $transparent);
            }
        }

        for ($y = 0; $y < self::HEIGHT; $y++) {
            for ($x = 0; $x < self::WIDTH; $x++) {
                $alpha = (imagecolorat($corners, $x, $y) >> 24) & 0x7F;
                if ($alpha === 127) {
                    continue;
                }
                $t = $y / self::HEIGHT;
                imagesetpixel($image, $x, $y, $this->blend($image, $from, $to, $t));
            }
        }
        imagedestroy($corners);

        $label = strtoupper($name);
        $size = $this->fitFontSize($label, $font, self::WIDTH - 120, 56, 32);
        $box = imagettfbbox($size, 0, $font, $label);

        $textX = intval((self::WIDTH - ($box[2] - $box[0])) / 2);
        $textY = intval((self::HEIGHT - ($box[1] - $box[7])) / 2);

        $white = imagecolorallocate($image, 255, 255, 255);
        imagettftext($image, $size, 0, $textX, $textY, $white, $font, $label);

        return $image;
    }

    private function fitFontSize(string $text, string $font, int $maxWidth, int $max, int $min): int
    {
        $size = $max;
        do {
            $box = imagettfbbox($size, 0, $font, $text);
            $width = $box[2] - $box[0];
            if ($width <= $maxWidth) {
                return $size;
            }
            $size -= 2;
        } while ($size >= $min);

        return $min;
    }

    private function toRgba(\GdImage $image, string $hex): int
    {
        [$r, $g, $b] = $this->hexToRgb($hex);

        return imagecolorallocate($image, $r, $g, $b);
    }

    private function blend(\GdImage $image, string $from, string $to, float $t): int
    {
        [$fr, $fg, $fb] = $this->hexToRgb($from);
        [$tr, $tg, $tb] = $this->hexToRgb($to);

        return imagecolorallocate(
            $image,
            intval($fr + ($tr - $fr) * $t),
            intval($fg + ($tg - $fg) * $t),
            intval($fb + ($tb - $fb) * $t),
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            intval(substr($hex, 0, 2), 16),
            intval(substr($hex, 2, 2), 16),
            intval(substr($hex, 4, 2), 16),
        ];
    }
}
