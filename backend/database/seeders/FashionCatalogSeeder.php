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

class FashionCatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var array<string, array<int, string>> */
    private array $categoryChildren = [
        "Women's Clothing" => ['Dresses', 'Tops & Blouses', 'Jeans & Trousers', 'Outerwear', 'Skirts & Shorts'],
        "Men's Clothing" => ['T-Shirts', 'Shirts', 'Jeans & Trousers', 'Hoodies & Jackets', 'Formal Wear'],
        'Kids Clothing' => ['Boys', 'Girls', 'Babies'],
    ];

    /** @var array<string, string> */
    private array $categoryImages = [
        "Women's Clothing" => 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=400&h=400&fit=crop',
        "Men's Clothing" => 'https://images.unsplash.com/photo-1617137968427-85924c800a22?w=400&h=400&fit=crop',
        'Kids Clothing' => 'https://images.unsplash.com/photo-1503919545889-aef636e10ad4?w=400&h=400&fit=crop',
    ];

    private static function image(string $id): string
    {
        return "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=900&q=80";
    }

    /**
     * @return array{name: string, brand: string, category: string, subcategory: string, images: array<int, string>, price: int, mrp: int, stock: int}
     */
    private static function women(string $name, string $brand, string $sub, array $images, int $price, int $mrp): array
    {
        return [
            'name' => $name,
            'brand' => $brand,
            'category' => "Women's Clothing",
            'subcategory' => $sub,
            'images' => array_map([self::class, 'image'], $images),
            'price' => $price,
            'mrp' => $mrp,
            'stock' => 40,
        ];
    }

    /**
     * @return array{name: string, brand: string, category: string, subcategory: string, images: array<int, string>, price: int, mrp: int, stock: int}
     */
    private static function men(string $name, string $brand, string $sub, array $images, int $price, int $mrp): array
    {
        return [
            'name' => $name,
            'brand' => $brand,
            'category' => "Men's Clothing",
            'subcategory' => $sub,
            'images' => array_map([self::class, 'image'], $images),
            'price' => $price,
            'mrp' => $mrp,
            'stock' => 45,
        ];
    }

    /**
     * @return array{name: string, brand: string, category: string, subcategory: string, images: array<int, string>, price: int, mrp: int, stock: int}
     */
    private static function kids(string $name, string $brand, string $sub, array $images, int $price, int $mrp): array
    {
        return [
            'name' => $name,
            'brand' => $brand,
            'category' => 'Kids Clothing',
            'subcategory' => $sub,
            'images' => array_map([self::class, 'image'], $images),
            'price' => $price,
            'mrp' => $mrp,
            'stock' => 60,
        ];
    }

    public function run(): void
    {
        $this->seedCategories();
        $this->seedBrands();

        $products = array_merge(
            [
                self::women('Floral Wrap Midi Dress', 'Casa Novo', 'Dresses', ['1595777457583-95e059d581b8', '1515372039744-b8f02a3ae446'], 1499, 2299),
                self::women('Classic Cotton Shirt Dress', 'Loom & Lane', 'Dresses', ['1434389677669-e08b4cac3105', '1562157873-818bc0726f68'], 1299, 1899),
                self::women('Emerald Satin Slip Dress', 'Casa Novo', 'Dresses', ['1572804013309-59a88b7e92f1', '1529139574466-a303027c1d8b'], 1799, 2799),
                self::women('Summer Boho Maxi Dress', 'Loom & Lane', 'Dresses', ['1529139574466-a303027c1d8b', '1515372039744-b8f02a3ae446'], 1599, 1999),
                self::women('Ribbed Knit Crop Top', 'Casa Novo', 'Tops & Blouses', ['1567401893414-76b7b1e5a7a5', '1562157873-818bc0726f68'], 699, 999),
                self::women('Oversized Graphic Tee', 'Loom & Lane', 'Tops & Blouses', ['1554568218-0f1715e72254', '1434389677669-e08b4cac3105'], 799, 1199),
                self::women('High-Rise Skinny Jeans', 'Casa Novo', 'Jeans & Trousers', ['1542272604-787c3835535d', '1541099649105-f69ad21f3246'], 1499, 2499),
                self::women('Straight-Leg Mom Jeans', 'Loom & Lane', 'Jeans & Trousers', ['1541099649105-f69ad21f3246', '1542272604-787c3835535d'], 1399, 2299),
                self::women('Belted Trench Coat', 'Casa Novo', 'Outerwear', ['1539533018447-63fcce2678e3', '1551028719-00167b16eac5'], 3499, 4999),
                self::women('Cropped Denim Jacket', 'Loom & Lane', 'Outerwear', ['1551028719-00167b16eac5', '1539533018447-63fcce2678e3'], 1899, 2799),
                self::women('Pleated Midi Skirt', 'Casa Novo', 'Skirts & Shorts', ['1567401893414-76b7b1e5a7a5', '1529139574466-a303027c1d8b'], 1199, 1799),
                self::women('Tailored Trouser Set', 'Casa Novo', 'Jeans & Trousers', ['1529139574466-a303027c1d8b', '1572804013309-59a88b7e92f1'], 2299, 3299),
                self::women('Rugby Stripe Polo Top', 'Loom & Lane', 'Tops & Blouses', ['1554568218-0f1715e72254', '1434389677669-e08b4cac3105'], 1099, 1599),
                self::women('Fluffy Knit Cardigan', 'Loom & Lane', 'Outerwear', ['1539533018447-63fcce2678e3', '1567401893414-76b7b1e5a7a5'], 1899, 2699),
            ],
            [
                self::men('Cotton Crew Neck Tee', 'Urban Thread', 'T-Shirts', ['1581655353564-df123a1eb820', '1550246140-29f40b909e5a'], 599, 899),
                self::men('Premium Oxford Shirt', 'Nordik Wear', 'Shirts', ['1618354691373-d851c5c3a990', '1598033129183-c4f50c736f10'], 1299, 1899),
                self::men('Slim Fit Formal Shirt', 'Nordik Wear', 'Shirts', ['1602810318383-e386cc2a3ccf', '1598033129183-c4f50c736f10'], 1499, 2199),
                self::men('Classic Pique Polo', 'Urban Thread', 'T-Shirts', ['1594938298603-c8148c4dae35', '1581655353564-df123a1eb820'], 999, 1499),
                self::men('Regular Fit Jeans', 'Nordik Wear', 'Jeans & Trousers', ['1584370848010-d7fe6bc767ec', '1542272604-787c3835535d'], 1399, 2299),
                self::men('Relaxed Chino Trousers', 'Urban Thread', 'Jeans & Trousers', ['1624378439575-d8705ad7ae80', '1584370848010-d7fe6bc767ec'], 1299, 1899),
                self::men('Zip-Up Fleece Hoodie', 'Urban Thread', 'Hoodies & Jackets', ['1578681994506-b8f463449011', '1556821840-3a63f95609a7'], 1699, 2499),
                self::men('Oversized Pullover Hoodie', 'Nordik Wear', 'Hoodies & Jackets', ['1556821840-3a63f95609a7', '1578681994506-b8f463449011'], 1799, 2599),
                self::men('Denim Trucker Jacket', 'Urban Thread', 'Hoodies & Jackets', ['1591047139829-d91aecb6caea', '1551028719-00167b16eac5'], 2199, 3199),
                self::men('Navy Blazer', 'Nordik Wear', 'Formal Wear', ['1507679799987-c73779587ccf', '1618354691373-d851c5c3a990'], 4299, 5999),
                self::men('Slim Tapered Trousers', 'Nordik Wear', 'Formal Wear', ['1624378439575-d8705ad7ae80', '1507679799987-c73779587ccf'], 1699, 2399),
                self::men('Graphic Print Tee', 'Urban Thread', 'T-Shirts', ['1554568218-0f1715e72254', '1581655353564-df123a1eb820'], 799, 1199),
                self::men('Linen Button-Down Shirt', 'Urban Thread', 'Shirts', ['1598033129183-c4f50c736f10', '1602810318383-e386cc2a3ccf'], 1199, 1699),
                self::men('Quilted Bomber Jacket', 'Nordik Wear', 'Hoodies & Jackets', ['1591047139829-d91aecb6caea', '1556821840-3a63f95609a7'], 2499, 3499),
            ],
            [
                self::kids('Striped Cotton Tee', 'Kinder Joy', 'Boys', ['1519238263530-99bdd11df2ea', '1580489944761-15a19d654956'], 449, 699),
                self::kids('Denim Overalls', 'Kinder Joy', 'Boys', ['1506794778202-cad84cf45f1d', '1519238263530-99bdd11df2ea'], 999, 1499),
                self::kids('Graphic Slogan Tee', 'Kinder Joy', 'Boys', ['1527325678964-54921661f888', '1580489944761-15a19d654956'], 499, 749),
                self::kids('Rainbow Stripe Tee', 'Kinder Joy', 'Girls', ['1559551409-dadc959f76b8', '1544027993-37dbfe43562a'], 449, 699),
                self::kids('Floral Cotton Dress', 'Kinder Joy', 'Girls', ['1522771930-78848d9293e8', '1596461404969-9ae70f2830c1'], 899, 1299),
                self::kids('Tutu Party Skirt', 'Kinder Joy', 'Girls', ['1503919545889-aef636e10ad4', '1559551409-dadc959f76b8'], 799, 1199),
                self::kids('Soft Knit Romper', 'Kinder Joy', 'Babies', ['1503342217505-b0a15ec3261c', '1544005313-94ddf0286df2'], 599, 899),
                self::kids('Footed Onesie Set', 'Kinder Joy', 'Babies', ['1544005313-94ddf0286df2', '1503342217505-b0a15ec3261c'], 699, 999),
                self::kids('Pastel Fleece Hoodie', 'Kinder Joy', 'Boys', ['1580489944761-15a19d654956', '1527325678964-54921661f888'], 799, 1199),
                self::kids('Ruffled Bloomer Set', 'Kinder Joy', 'Girls', ['1596461404969-9ae70f2830c1', '1522771930-78848d9293e8'], 749, 1099),
                self::kids('Winter Puffer Jacket', 'Kinder Joy', 'Boys', ['1503919545889-aef636e10ad4', '1506794778202-cad84cf45f1d'], 1499, 2199),
                self::kids('Cozy Fleece Pajama Set', 'Kinder Joy', 'Girls', ['1527325678964-54921661f888', '1503919545889-aef636e10ad4'], 949, 1399),
            ],
        );

        $vendors = VendorProfile::query()->where('is_approved', true)->orderBy('id')->get();
        $vendorCount = $vendors->count();

        if ($vendorCount === 0) {
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
                    'description' => "Shop the latest {$name} collection.",
                    'image' => $this->categoryImages[$name],
                    'is_active' => true,
                    'sort_order' => $sort++,
                ],
            );

            foreach ($children as $index => $childName) {
                Category::query()->firstOrCreate(
                    ['parent_id' => $parent->id, 'name' => $childName],
                    [
                        'slug' => $parent->slug.'-'.Str::slug($childName),
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
            'Casa Novo' => 'https://images.unsplash.com/photo-1598033129183-c4f50c736f10?w=400&h=400&fit=crop',
            'Loom & Lane' => 'https://images.unsplash.com/photo-1445205170230-053b83016050?w=400&h=400&fit=crop',
            'Urban Thread' => 'https://images.unsplash.com/photo-1556821840-3a63f95609a7?w=400&h=400&fit=crop',
            'Nordik Wear' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=400&h=400&fit=crop',
            'Kinder Joy' => 'https://images.unsplash.com/photo-1471286174890-9c112ffca5b4?w=400&h=400&fit=crop',
        ];

        foreach ($brands as $name => $logo) {
            Brand::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'logo' => $logo,
                    'description' => "{$name} — premium clothing brand.",
                    'meta_title' => "{$name} - Buy Online",
                    'meta_description' => "Shop {$name} clothing collection online.",
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @param  array<int, array{name: string, brand: string, category: string, subcategory: string, images: array<int, string>, price: int, mrp: int, stock: int}>  $products
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
                    'short_description' => "Stylish {$data['name']} crafted for everyday comfort.",
                    'description' => "A versatile piece from the {$data['category']} collection. ".
                        'Made from premium, breathable fabric with a clean finish and a durable build that keeps you comfortable all day.',
                    'specifications' => ['Material' => 'Cotton blend', 'Fit' => 'Regular', 'Care' => 'Machine wash cold'],
                    'tax_class' => 'standard',
                    'gst' => 5,
                    'hsn_code' => '6109',
                    'price' => $data['price'],
                    'mrp' => $data['mrp'],
                    'stock' => $data['stock'],
                    'reserved_stock' => 0,
                    'sku' => 'APP-'.strtoupper(Str::random(8)),
                    'images' => $data['images'],
                    'attributes' => [],
                    'is_active' => true,
                    'status' => 'PUBLISHED',
                    'approval_status' => 'APPROVED',
                    'approved_at' => now(),
                    'featured' => in_array($index, [0, 1, 4, 7, 8, 14, 15, 16, 19, 20, 28, 31, 36, 38], true),
                    'best_seller' => in_array($index, [2, 5, 9, 11, 13, 17, 22, 24, 27, 30, 33, 35, 37, 39], true),
                    'is_returnable' => true,
                    'return_days' => 15,
                    'return_policy' => 'Return this item within 15 days of delivery for a full refund. Items must be unused and in original packaging.',
                    'created_by' => null,
                ],
            );
        }
    }
}
