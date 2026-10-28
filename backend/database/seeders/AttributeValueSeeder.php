<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AttributeValueSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $values = [
            'Color' => ['Red', 'Blue', 'Black', 'White', 'Green'],
            'Size' => ['S', 'M', 'L', 'XL', 'XXL'],
            'RAM' => ['4 GB', '6 GB', '8 GB', '12 GB', '16 GB'],
            'Storage' => ['32 GB', '64 GB', '128 GB', '256 GB', '512 GB'],
            'Material' => ['Cotton', 'Polyester', 'Leather', 'Metal', 'Glass'],
            'Weight' => ['0.5 kg', '1 kg', '2 kg', '5 kg'],
            'Capacity' => ['500 ml', '1 L', '2 L', '5 L', '10 L'],
            'Connectivity' => ['Wi-Fi', 'Bluetooth', 'USB-C', 'AUX'],
            'Warranty' => ['6 Months', '1 Year', '2 Years'],
            'Screen Size' => ['11"', '13"', '15"', '17"'],
        ];

        foreach ($values as $attributeName => $attributeValues) {
            $attribute = Attribute::query()->firstOrCreate(
                ['name' => $attributeName],
                [
                    'slug' => Str::slug($attributeName),
                    'type' => 'SELECT',
                    'is_active' => true,
                ],
            );

            foreach ($attributeValues as $index => $value) {
                AttributeValue::query()->firstOrCreate(
                    ['attribute_id' => $attribute->id, 'value' => $value],
                    [
                        'slug' => Str::slug($value),
                        'sort_order' => $index,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
