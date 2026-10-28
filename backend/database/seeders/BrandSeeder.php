<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $brands = [
            'Apple',
            'Samsung',
            'Sony',
            'Nike',
            'Adidas',
            'Puma',
            'Levi\'s',
            'Zara',
            'H&M',
            'Boat',
        ];

        foreach ($brands as $name) {
            Brand::query()->firstOrCreate(
                ['name' => $name],
                [
                    'slug' => Str::slug($name),
                    'description' => "{$name} official brand.",
                    'logo' => null,
                    'meta_title' => "{$name} - Buy Online",
                    'meta_description' => "Shop latest {$name} products at best prices.",
                    'is_active' => true,
                ],
            );
        }
    }
}
