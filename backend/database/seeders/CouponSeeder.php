<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CouponSeeder extends Seeder
{
    /** @var array<int, array<string, mixed>> */
    private const COUPONS = [
        [
            'code' => 'WELCOME10', 'type' => 'PERCENTAGE', 'value' => 10, 'min_amount' => 500,
            'max_discount' => 200, 'first_order_only' => true, 'usage_limit' => null, 'per_user_limit' => 1,
            'is_active' => true, 'expires_in_days' => 60, 'created_days_ago' => 40,
            'usages' => [
                ['order' => 17, 'discount' => 85.50],
                ['order' => 18, 'discount' => 122.00],
                ['order' => 19, 'discount' => 164.00],
            ],
        ],
        [
            'code' => 'SAVE15', 'type' => 'PERCENTAGE', 'value' => 15, 'min_amount' => 1500,
            'max_discount' => 500, 'first_order_only' => false, 'usage_limit' => 50, 'per_user_limit' => 1,
            'is_active' => true, 'vendor_id' => 2, 'expires_in_days' => 30, 'created_days_ago' => 28,
            'brand_ids' => [1, 2, 3],
            'usages' => [
                ['order' => 8, 'discount' => 175.00],
                ['order' => 9, 'discount' => 240.00],
                ['order' => 10, 'discount' => 310.50],
                ['order' => 11, 'discount' => 195.00],
            ],
        ],
        [
            'code' => 'FLAT200', 'type' => 'FIXED', 'value' => 200, 'min_amount' => 999,
            'max_discount' => null, 'first_order_only' => false, 'usage_limit' => 200, 'per_user_limit' => 1,
            'is_active' => true, 'expires_in_days' => 45, 'created_days_ago' => 22,
            'category_ids' => [1, 5, 12],
            'usages' => [
                ['order' => 12, 'discount' => 200.00],
                ['order' => 14, 'discount' => 200.00],
                ['order' => 16, 'discount' => 200.00],
            ],
        ],
        [
            'code' => 'SUMMER20', 'type' => 'PERCENTAGE', 'value' => 20, 'min_amount' => 2000,
            'max_discount' => 800, 'first_order_only' => false, 'usage_limit' => null, 'per_user_limit' => 1,
            'is_active' => true, 'expires_in_days' => 20, 'created_days_ago' => 18,
            'category_ids' => [3, 8],
            'usages' => [
                ['order' => 13, 'discount' => 480.00],
                ['order' => 15, 'discount' => 620.00],
            ],
        ],
        [
            'code' => 'EXTRA500', 'type' => 'FIXED', 'value' => 500, 'min_amount' => 2999,
            'max_discount' => null, 'first_order_only' => false, 'usage_limit' => 100, 'per_user_limit' => 1,
            'is_active' => true, 'vendor_id' => 3, 'expires_in_days' => 40, 'created_days_ago' => 12,
            'product_ids' => [1, 5, 8, 12],
            'usages' => [
                ['order' => 20, 'discount' => 500.00],
            ],
        ],
        [
            'code' => 'WEEKEND10', 'type' => 'PERCENTAGE', 'value' => 10, 'min_amount' => 1000,
            'max_discount' => 300, 'first_order_only' => false, 'usage_limit' => 30, 'per_user_limit' => 2,
            'is_active' => true, 'expires_in_days' => 12, 'created_days_ago' => 8,
            'usages' => [],
        ],
        [
            'code' => 'MEGAOFF', 'type' => 'PERCENTAGE', 'value' => 40, 'min_amount' => 5000,
            'max_discount' => 1500, 'first_order_only' => false, 'usage_limit' => 5, 'per_user_limit' => 1,
            'is_active' => true, 'expires_in_days' => 5, 'created_days_ago' => 3,
            'category_ids' => [2, 6, 9],
            'product_ids' => [6, 7, 10, 11],
            'brand_ids' => [4, 5, 6, 7],
            'vendor_ids' => [1, 4, 5],
            'usages' => [],
        ],
        [
            'code' => 'FLATANNUAL', 'type' => 'FIXED', 'value' => 1000, 'min_amount' => 5000,
            'max_discount' => null, 'first_order_only' => false, 'usage_limit' => 20, 'per_user_limit' => 1,
            'is_active' => true, 'expires_in_days' => -10, 'created_days_ago' => 50,
            'usages' => [],
        ],
        [
            'code' => 'DISABLED5', 'type' => 'PERCENTAGE', 'value' => 5, 'min_amount' => 300,
            'max_discount' => 100, 'first_order_only' => false, 'usage_limit' => 500, 'per_user_limit' => 1,
            'is_active' => false, 'expires_in_days' => 90, 'created_days_ago' => 15,
            'usages' => [],
        ],
        [
            'code' => 'STORE5', 'type' => 'PERCENTAGE', 'value' => 5, 'min_amount' => 499,
            'max_discount' => 150, 'first_order_only' => false, 'usage_limit' => null, 'per_user_limit' => 1,
            'is_active' => true, 'vendor_id' => 2, 'expires_in_days' => 30, 'created_days_ago' => 1,
            'usages' => [],
        ],
        [
            'code' => 'DRESS15', 'type' => 'PERCENTAGE', 'value' => 15, 'min_amount' => 999,
            'max_discount' => 400, 'first_order_only' => false, 'usage_limit' => null, 'per_user_limit' => 1,
            'is_active' => true, 'expires_in_days' => 30, 'created_days_ago' => 1,
            'category_slugs' => ['lehenga'],
            'usages' => [],
        ],
        [
            'code' => 'BRAND10', 'type' => 'PERCENTAGE', 'value' => 10, 'min_amount' => 799,
            'max_discount' => 300, 'first_order_only' => false, 'usage_limit' => null, 'per_user_limit' => 1,
            'is_active' => true, 'expires_in_days' => 30, 'created_days_ago' => 1,
            'brand_slugs' => ['mehr-atelier'],
            'usages' => [],
        ],
    ];

    public function run(): void
    {
        if (Coupon::query()->count() > 0) {
            $this->command?->info('Coupons already seeded. Skipping.');

            return;
        }

        $created = [];

        DB::transaction(function () use (&$created): void {
            foreach (self::COUPONS as $data) {
                $coupon = Coupon::query()->create([
                    'vendor_id' => $data['vendor_id'] ?? null,
                    'code' => $data['code'],
                    'type' => $data['type'],
                    'value' => $data['value'],
                    'min_amount' => $data['min_amount'],
                    'max_discount' => $data['max_discount'],
                    'starts_at' => now()->subDays($data['created_days_ago'] - 1)->setTime(0, 0),
                    'expires_at' => now()->addDays($data['expires_in_days'])->setTime(23, 59),
                    'usage_limit' => $data['usage_limit'],
                    'per_user_limit' => $data['per_user_limit'],
                    'first_order_only' => $data['first_order_only'],
                    'is_active' => $data['is_active'],
                    'used_count' => count($data['usages']),
                    'created_at' => now()->subDays($data['created_days_ago']),
                    'updated_at' => now()->subDays($data['created_days_ago']),
                ]);

                foreach (['category_ids' => 'categories', 'brand_ids' => 'brands', 'product_ids' => 'products', 'vendor_ids' => 'vendors'] as $inputKey => $relation) {
                    if (empty($data[$inputKey])) {
                        continue;
                    }

                    $model = match ($relation) {
                        'categories' => Category::class,
                        'brands' => Brand::class,
                        'products' => Product::class,
                        default => VendorProfile::class,
                    };

                    $coupon->$relation()->sync(
                        $model::query()->whereIn('id', $data[$inputKey])->pluck('id')->all(),
                    );
                }

                foreach (['category_slugs' => 'categories', 'brand_slugs' => 'brands'] as $inputKey => $relation) {
                    if (empty($data[$inputKey])) {
                        continue;
                    }

                    $model = $relation === 'categories' ? Category::class : Brand::class;

                    $coupon->$relation()->sync(
                        $model::query()->whereIn('slug', $data[$inputKey])->pluck('id')->all(),
                    );
                }

                foreach ($data['usages'] as $i => $usage) {
                    $order = Order::query()->find($usage['order']);

                    if (! $order) {
                        continue;
                    }

                    $coupon->usages()->create([
                        'order_id' => $order->id,
                        'user_id' => $order->user_id,
                        'discount_amount' => $usage['discount'],
                        'created_at' => now()->subDays((int) $data['created_days_ago'] - $i)->setTime(rand(10, 20), rand(0, 59)),
                    ]);

                    Order::query()->where('id', $order->id)->update(['coupon_id' => $coupon->id]);
                }

                $created[] = ['coupon' => $coupon, 'days' => (int) $data['created_days_ago']];
            }
        });

        DB::transaction(function () use ($created): void {
            foreach ($created as $entry) {
                $entry['coupon']->update([
                    'created_at' => now()->subDays($entry['days'])->setTime(rand(8, 18), rand(0, 59)),
                    'updated_at' => now()->subDays(rand(0, $entry['days']))->setTime(rand(8, 18), rand(0, 59)),
                ]);
            }
        });

        $this->command?->info('Seeded '.count($created).' coupons with usage history.');
    }
}
