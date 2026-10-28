<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(int $perVendor = 10): void
    {
        $vendors = VendorProfile::query()->where('is_approved', true)->get();

        foreach ($vendors as $vendor) {
            $existing = Product::query()->where('vendor_id', $vendor->id)->count();

            if ($existing < $perVendor) {
                Product::factory()
                    ->count($perVendor - $existing)
                    ->create([
                        'vendor_id' => $vendor->id,
                        'category_id' => Category::query()->inRandomOrder()->first()?->id,
                        'created_by' => null,
                    ]);
            }
        }

        $this->flagProductSelection('featured', 12);
        $this->flagProductSelection('best_seller', 8);
    }

    protected function flagProductSelection(string $column, int $limit): void
    {
        $ids = Product::query()->live()->inRandomOrder()->limit($limit)->pluck('id');

        Product::query()->whereIn('id', $ids)->update([$column => true]);
    }
}
