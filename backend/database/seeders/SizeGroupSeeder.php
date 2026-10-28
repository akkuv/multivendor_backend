<?php

namespace Database\Seeders;

use App\Models\SizeGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SizeGroupSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $groups = [
            'Clothing' => 'Standard apparel sizes for t-shirts, shirts and jackets.',
            'Shoes' => 'Footwear sizes in UK/US scales.',
            'Pants' => 'Waist and length based trouser sizes.',
            'Kids' => 'Size charts for children.',
            'Rings' => 'Finger ring sizes.',
        ];

        foreach ($groups as $name => $description) {
            SizeGroup::query()->firstOrCreate(
                ['name' => $name],
                [
                    'slug' => Str::slug($name),
                    'description' => $description,
                    'is_active' => true,
                ],
            );
        }
    }
}
