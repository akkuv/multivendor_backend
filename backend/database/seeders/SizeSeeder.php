<?php

namespace Database\Seeders;

use App\Models\Size;
use App\Models\SizeGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SizeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $groups = [
            'Clothing' => ['S', 'M', 'L', 'XL', 'XXL'],
            'Shoes' => ['6', '7', '8', '9', '10', '11'],
            'Pants' => ['28', '30', '32', '34', '36'],
            'Kids' => ['2-3Y', '4-5Y', '6-7Y', '8-9Y'],
            'Rings' => ['5', '6', '7', '8', '9', '10'],
        ];

        foreach ($groups as $groupName => $sizeValues) {
            $group = SizeGroup::query()->firstOrCreate(
                ['name' => $groupName],
                [
                    'slug' => Str::slug($groupName),
                    'is_active' => true,
                ],
            );

            foreach ($sizeValues as $index => $value) {
                Size::query()->firstOrCreate(
                    ['size_group_id' => $group->id, 'name' => $value],
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
