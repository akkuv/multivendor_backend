<?php

namespace Database\Seeders;

use App\Models\Color;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ColorSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $colors = [
            'Black' => '#000000',
            'White' => '#FFFFFF',
            'Red' => '#FF0000',
            'Blue' => '#0000FF',
            'Green' => '#008000',
            'Yellow' => '#FFFF00',
            'Grey' => '#808080',
            'Navy' => '#000080',
            'Beige' => '#F5F5DC',
            'Purple' => '#800080',
            'Burgundy' => '#6E1D2A',
            'Forest' => '#1F5B35',
            'Rose' => '#C94A6B',
            'Teal' => '#0F7C7F',
            'Olive' => '#6B7F2F',
            'Stone' => '#ACA49B',
            'Camel' => '#BF8F63',
            'Charcoal' => '#3A3F44',
            'Sky' => '#6FB5D6',
            'Mustard' => '#D9A423',
            'Mint' => '#7FCFA0',
            'Coral' => '#EF6F5C',
        ];

        foreach ($colors as $name => $hexCode) {
            Color::query()->updateOrCreate(
                ['name' => $name],
                [
                    'slug' => Str::slug($name),
                    'hex_code' => $hexCode,
                    'is_active' => true,
                ],
            );
        }
    }
}
