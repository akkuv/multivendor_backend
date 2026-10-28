<?php

namespace Database\Seeders;

use App\Models\CommissionSetting;
use Illuminate\Database\Seeder;

class CommissionSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['name' => 'default', 'rate' => 10.00, 'type' => 'PERCENTAGE', 'is_active' => true],
            ['name' => 'electronics', 'rate' => 8.00, 'type' => 'PERCENTAGE', 'is_active' => true],
            ['name' => 'fashion', 'rate' => 12.00, 'type' => 'PERCENTAGE', 'is_active' => true],
            ['name' => 'grocery', 'rate' => 5.00, 'type' => 'PERCENTAGE', 'is_active' => true],
        ];

        foreach ($settings as $setting) {
            CommissionSetting::query()->updateOrCreate(
                ['name' => $setting['name']],
                $setting,
            );
        }
    }
}
