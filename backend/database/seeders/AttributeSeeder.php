<?php

namespace Database\Seeders;

use App\Models\Attribute;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AttributeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $attributes = [
            'Color' => 'SELECT',
            'Size' => 'SELECT',
            'RAM' => 'BUTTON',
            'Storage' => 'BUTTON',
            'Material' => 'RADIO',
            'Weight' => 'RADIO',
            'Capacity' => 'BUTTON',
            'Connectivity' => 'RADIO',
            'Warranty' => 'SELECT',
            'Screen Size' => 'BUTTON',
        ];

        foreach ($attributes as $name => $type) {
            Attribute::query()->firstOrCreate(
                ['name' => $name],
                [
                    'slug' => Str::slug($name),
                    'type' => $type,
                    'is_active' => true,
                ],
            );
        }
    }
}
