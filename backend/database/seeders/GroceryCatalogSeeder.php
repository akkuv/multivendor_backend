<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\VendorProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GroceryCatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var array<string, array<int, string>> */
    private array $categoryChildren = [
        'Grocery' => ['Staples', 'Dairy', 'Snacks'],
    ];

    /** @var array<string, string> */
    private array $categoryImages = [
        'Grocery' => 'https://images.unsplash.com/photo-1585032226651-759b368d7246?w=400&h=400&fit=crop',
    ];

    private static function image(string $id): string
    {
        return "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=900&q=80";
    }

    /**
     * @return array{name: string, brand: string, category: string, subcategory: string, images: array<int, string>, price: int, mrp: int, stock: int, hsn: string, returnable: bool}
     */
    private static function staples(string $name, string $brand, array $images, int $price, int $mrp, string $hsn, bool $returnable = false): array
    {
        return [
            'name' => $name,
            'brand' => $brand,
            'category' => 'Grocery',
            'subcategory' => 'Staples',
            'images' => array_map([self::class, 'image'], $images),
            'price' => $price,
            'mrp' => $mrp,
            'stock' => 90,
            'hsn' => $hsn,
            'returnable' => $returnable,
        ];
    }

    /**
     * @return array{name: string, brand: string, category: string, subcategory: string, images: array<int, string>, price: int, mrp: int, stock: int, hsn: string, returnable: bool}
     */
    private static function dairy(string $name, string $brand, array $images, int $price, int $mrp, string $hsn, bool $returnable = false): array
    {
        return [
            'name' => $name,
            'brand' => $brand,
            'category' => 'Grocery',
            'subcategory' => 'Dairy',
            'images' => array_map([self::class, 'image'], $images),
            'price' => $price,
            'mrp' => $mrp,
            'stock' => 70,
            'hsn' => $hsn,
            'returnable' => $returnable,
        ];
    }

    /**
     * @return array{name: string, brand: string, category: string, subcategory: string, images: array<int, string>, price: int, mrp: int, stock: int, hsn: string, returnable: bool}
     */
    private static function snacks(string $name, string $brand, array $images, int $price, int $mrp, string $hsn, bool $returnable = false): array
    {
        return [
            'name' => $name,
            'brand' => $brand,
            'category' => 'Grocery',
            'subcategory' => 'Snacks',
            'images' => array_map([self::class, 'image'], $images),
            'price' => $price,
            'mrp' => $mrp,
            'stock' => 110,
            'hsn' => $hsn,
            'returnable' => $returnable,
        ];
    }

    public function run(): void
    {
        $this->seedCategories();
        $this->seedBrands();

        $products = array_merge(
            [
                self::staples('Extra Long Basmati Rice 5kg', 'Golden Harvest', ['1586201375761-83865001e31c', '1542838132-92c53300491e'], 649, 799, '1006'),
                self::staples('Whole Wheat Atta 5kg', 'Golden Harvest', ['1466637574441-749b8f19452f', '1509440159596-0249088772ff'], 329, 429, '1101'),
                self::staples('Toor Dal 1kg', 'Golden Harvest', ['1542838132-92c53300491e', '1488459716781-31db52582fe9'], 189, 229, '0713'),
                self::staples('Masoor Dal 1kg', 'Golden Harvest', ['1542838132-92c53300491e', '1488459716781-31db52582fe9'], 169, 209, '0713'),
                self::staples('Refined Sunflower Oil 1L', 'Golden Harvest', ['1467003909585-2f8a72700288', '1556911220-bff31c812dba'], 165, 199, '1512'),
                self::staples('Kachi Ghani Mustard Oil 1L', 'Golden Harvest', ['1467003909585-2f8a72700288', '1556911220-bff31c812dba'], 195, 239, '1514'),
                self::staples('Organic Sugar 1kg', 'Golden Harvest', ['1585032226651-759b368d7246', '1504674900247-0877df9cc836'], 69, 99, '1701'),
                self::staples('Iodized Salt 1kg', 'Golden Harvest', ['1585032226651-759b368d7246', '1504674900247-0877df9cc836'], 29, 49, '2501'),
                self::staples('Classic Green Tea 250g', 'Golden Harvest', ['1544787219-7f47ccb76574', '1554118811-1e0d58224f24'], 249, 329, '0902'),
                self::staples('Instant Coffee Jar 200g', 'Golden Harvest', ['1509042239860-f550ce710b93', '1511920170033-f8396924c348'], 549, 699, '0901'),
                self::staples('Rolled Oats 1kg', 'Golden Harvest', ['1596797038530-2c107229654b', '1546069901-ba9599a7e63c'], 289, 349, '1004'),
                self::staples('Roasted Peanuts 500g', 'Golden Harvest', ['1546069901-ba9599a7e63c', '1504674900247-0877df9cc836'], 129, 169, '1202'),
            ],
            [
                self::dairy('Fresh Toned Milk 1L', 'Daily Fresh', ['1550583724-b2692b85b150', '1585032226651-759b368d7246'], 62, 75, '0401'),
                self::dairy('Full Cream Milk 500ml', 'Daily Fresh', ['1550583724-b2692b85b150', '1585032226651-759b368d7246'], 37, 45, '0401'),
                self::dairy('Fresh Curd 400g', 'Daily Fresh', ['1504674900247-0877df9cc836', '1546069901-ba9599a7e63c'], 45, 55, '0403'),
                self::dairy('Greek Yogurt 400g', 'Daily Fresh', ['1546069901-ba9599a7e63c', '1504674900247-0877df9cc836'], 149, 189, '0403'),
                self::dairy('Salted Butter 100g', 'Daily Fresh', ['1550583724-b2692b85b150', '1586201375761-83865001e31c'], 55, 70, '0405'),
                self::dairy('Pure Desi Ghee 500ml', 'Daily Fresh', ['1550583724-b2692b85b150', '1556911220-bff31c812dba'], 385, 459, '0405'),
                self::dairy('Cheddar Cheese Slices 200g', 'Daily Fresh', ['1550583724-b2692b85b150', '1509440159596-0249088772ff'], 189, 229, '0406'),
                self::dairy('Mozzarella Cheese 200g', 'Daily Fresh', ['1565299624946-b28f40a0ae38', '1550583724-b2692b85b150'], 219, 269, '0406'),
                self::dairy('Fresh Malai Paneer 200g', 'Daily Fresh', ['1565299624946-b28f40a0ae38', '1546069901-ba9599a7e63c'], 89, 109, '0406'),
                self::dairy('Masala Buttermilk 1L', 'Daily Fresh', ['1550583724-b2692b85b150', '1504674900247-0877df9cc836'], 49, 59, '0403'),
                self::dairy('Cream Cheese Spread 150g', 'Daily Fresh', ['1546069901-ba9599a7e63c', '1586201375761-83865001e31c'], 159, 199, '0406'),
                self::dairy('Flavored Yogurt Drink 1L', 'Daily Fresh', ['1550583724-b2692b85b150', '1585032226651-759b368d7246'], 89, 109, '0403'),
            ],
            [
                self::snacks('Classic Salted Potato Chips 100g', 'SnackJoy', ['1518977676601-b53f82aba655', '1504674900247-0877df9cc836'], 49, 70, '2005'),
                self::snacks('Peri Peri Nachos 150g', 'SnackJoy', ['1504674900247-0877df9cc836', '1596797038530-2c107229654b'], 79, 99, '1905'),
                self::snacks('Butter Honey Popcorn 150g', 'SnackJoy', ['1518977676601-b53f82aba655', '1546069901-ba9599a7e63c'], 99, 129, '1904'),
                self::snacks('Milk Chocolate Bar 120g', 'SnackJoy', ['1585032226651-759b368d7246', '1414235077428-338989a2e8c0'], 129, 159, '1806'),
                self::snacks('Choco Cream Wafers 6-pack', 'SnackJoy', ['1414235077428-338989a2e8c0', '1509440159596-0249088772ff'], 69, 89, '1905'),
                self::snacks('Classic Digestive Biscuits 300g', 'SnackJoy', ['1509440159596-0249088772ff', '1596797038530-2c107229654b'], 59, 79, '1905'),
                self::snacks('Roasted Salted Cashews 200g', 'SnackJoy', ['1490474418585-ba9bad8fd0ea', '1546069901-ba9599a7e63c'], 299, 379, '0801'),
                self::snacks('Dry Fruit Trail Mix 250g', 'SnackJoy', ['1490474418585-ba9bad8fd0ea', '1488459716781-31db52582fe9'], 349, 429, '2006'),
                self::snacks('Instant Masala Noodles 5-pack', 'SnackJoy', ['1414235077428-338989a2e8c0', '1504674900247-0877df9cc836'], 89, 119, '1902'),
                self::snacks('Fruit Energy Bars 8-pack', 'SnackJoy', ['1596797038530-2c107229654b', '1490474418585-ba9bad8fd0ea'], 179, 229, '1806'),
                self::snacks('Honey Roasted Peanuts 250g', 'SnackJoy', ['1546069901-ba9599a7e63c', '1518977676601-b53f82aba655'], 149, 189, '2008'),
                self::snacks('Assorted Candy Jar 500g', 'SnackJoy', ['1490474418585-ba9bad8fd0ea', '1585032226651-759b368d7246'], 199, 249, '1704'),
            ],
        );

        $vendors = VendorProfile::query()->where('is_approved', true)->orderBy('id')->get();

        if ($vendors->isEmpty()) {
            return;
        }

        $this->seedProducts($products, $vendors);
    }

    protected function seedCategories(): void
    {
        $sort = 0;

        foreach ($this->categoryChildren as $name => $children) {
            $parent = Category::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => 'Order fresh groceries and everyday essentials online.',
                    'image' => $this->categoryImages[$name],
                    'is_active' => true,
                    'sort_order' => $sort++,
                ],
            );

            foreach ($children as $index => $childName) {
                Category::query()->firstOrCreate(
                    ['parent_id' => $parent->id, 'name' => $childName],
                    [
                        'slug' => Str::slug($childName),
                        'description' => "{$childName} in {$name}",
                        'is_active' => true,
                        'sort_order' => $index,
                    ],
                );
            }
        }
    }

    protected function seedBrands(): void
    {
        $brands = [
            'Golden Harvest' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=400&h=400&fit=crop',
            'Daily Fresh' => 'https://images.unsplash.com/photo-1550583724-b2692b85b150?w=400&h=400&fit=crop',
            'SnackJoy' => 'https://images.unsplash.com/photo-1518977676601-b53f82aba655?w=400&h=400&fit=crop',
        ];

        foreach ($brands as $name => $logo) {
            Brand::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'logo' => $logo,
                    'description' => "{$name} — fresh, quality grocery products.",
                    'meta_title' => "{$name} - Buy Online",
                    'meta_description' => "Shop {$name} grocery essentials online.",
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @param  array<int, array{name: string, brand: string, category: string, subcategory: string, images: array<int, string>, price: int, mrp: int, stock: int, hsn: string, returnable: bool}>  $products
     * @param  Collection<int, VendorProfile>  $vendors
     */
    protected function seedProducts(array $products, $vendors): void
    {
        $categories = Category::query()->whereNull('parent_id')->get()->keyBy('name');
        $brands = Brand::query()->get()->keyBy('name');

        foreach ($products as $index => $data) {
            $parent = $categories[$data['category']] ?? null;
            $brand = $brands[$data['brand']] ?? null;

            if (! $parent || ! $brand) {
                continue;
            }

            $subcategory = $parent->children()->where('name', $data['subcategory'])->first();
            $vendor = $vendors[$index % $vendors->count()];
            $name = $data['name'];

            Product::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'vendor_id' => $vendor->id,
                    'category_id' => $parent->id,
                    'subcategory_id' => $subcategory?->id ?? null,
                    'brand_id' => $brand->id,
                    'name' => $name,
                    'short_description' => "Fresh {$data['name']} delivered to your doorstep.",
                    'description' => ucfirst($data['subcategory']).' staple from the '.$data['brand'].' range. '.
                        'Sourced for freshness and quality, packed in hygienic, tamper-proof packaging for everyday use.',
                    'specifications' => ['Net Quantity' => null, 'Packaging' => 'Food-grade sealed pack', 'Country of Origin' => 'India'],
                    'tax_class' => 'standard',
                    'gst' => 5,
                    'hsn_code' => $data['hsn'],
                    'price' => $data['price'],
                    'mrp' => $data['mrp'],
                    'stock' => $data['stock'],
                    'reserved_stock' => 0,
                    'sku' => 'GRO-'.strtoupper(Str::random(8)),
                    'images' => $data['images'],
                    'attributes' => [],
                    'is_active' => true,
                    'status' => 'PUBLISHED',
                    'approval_status' => 'APPROVED',
                    'approved_at' => now(),
                    'featured' => in_array($index, [0, 3, 6, 13, 15, 18, 24, 27, 30], true),
                    'best_seller' => in_array($index, [1, 5, 8, 12, 16, 21, 25, 29, 33, 35], true),
                    'is_returnable' => $data['returnable'],
                    'return_days' => $data['returnable'] ? 7 : 0,
                    'return_policy' => $data['returnable']
                        ? 'Return within 7 days of delivery if the item is unopened and in original packaging.'
                        : 'Perishable and food items are not eligible for returns once delivered.',
                    'created_by' => null,
                ],
            );
        }
    }
}
