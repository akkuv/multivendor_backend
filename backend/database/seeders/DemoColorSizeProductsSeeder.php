<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VendorProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoColorSizeProductsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @return array<int, array{
     *     name: string,
     *     brand: string,
     *     category: string,
     *     subcategory: string,
     *     photos: array<string, string>,
     *     angles: array<int, string>,
     *     price: int,
     *     mrp: int,
     *     colors: array<int, string>,
     *     sizes: array<int, string>,
     *     out: array{string, string}|null,
     * }>
     */
    private function products(): array
    {
        return [
            [
                'name' => 'Demo Colour Tee',
                'brand' => 'Urban Thread',
                'category' => "Men's Clothing",
                'subcategory' => 'T-Shirts',
                'photos' => [
                    'Black' => '1576566588028-4147f3842f27',
                    'White' => '1521572163474-6864f9cf17ab',
                    'Red' => '1556821840-3a63f95609a7',
                    'Blue' => '1578681994506-b8f463449011',
                    'Green' => '1544022613-e87ca75a784a',
                ],
                'angles' => ['1521572163474-6864f9cf17ab', '1596755094514-f87e34085b2c'],
                'price' => 599,
                'mrp' => 999,
                'colors' => ['Black', 'White', 'Red', 'Blue', 'Green'],
                'sizes' => ['S', 'M', 'L', 'XL', 'XXL'],
                'out' => ['Black', 'L'],
            ],
            [
                'name' => 'Demo Colour Wrap Dress',
                'brand' => 'Casa Novo',
                'category' => "Women's Clothing",
                'subcategory' => 'Dresses',
                'photos' => [
                    'Burgundy' => '1595777457583-95e059d581b8',
                    'Rose' => '1515372039744-b8f02a3ae446',
                    'Mustard' => '1583496661160-fb5886a0aaaa',
                    'Teal' => '1580489944761-15a19d654956',
                    'White' => '1594938298603-c8148c4dae35',
                ],
                'angles' => ['1583496661160-fb5886a0aaaa', '1515372039744-b8f02a3ae446'],
                'price' => 1499,
                'mrp' => 2299,
                'colors' => ['Burgundy', 'Rose', 'Mustard', 'Teal', 'White'],
                'sizes' => ['XS', 'S', 'M', 'L', 'XL'],
                'out' => ['White', 'XS'],
            ],
            [
                'name' => 'Demo Colour Jeans',
                'brand' => 'Nordik Wear',
                'category' => "Men's Clothing",
                'subcategory' => 'Jeans & Trousers',
                'photos' => [
                    'Blue' => '1584370848010-d7fe6bc767ec',
                    'Black' => '1542272604-787c3835535d',
                    'Grey' => '1471286174890-9c112ffca5b4',
                    'Stone' => '1544022613-e87ca75a784a',
                ],
                'angles' => ['1542272604-787c3835535d', '1471286174890-9c112ffca5b4'],
                'price' => 1399,
                'mrp' => 2299,
                'colors' => ['Blue', 'Black', 'Grey', 'Stone'],
                'sizes' => ['28', '30', '32', '34', '36'],
                'out' => ['Stone', '32'],
            ],
            [
                'name' => 'Demo Colour Kids Tee',
                'brand' => 'Kinder Joy',
                'category' => 'Fashion',
                'subcategory' => 'Kids',
                'photos' => [
                    'Sky' => '1519238263530-99bdd11df2ea',
                    'Mint' => '1580489944761-15a19d654956',
                    'Coral' => '1503919545889-aef636e10ad4',
                    'Mustard' => '1555252333-9f8e92e65df9',
                ],
                'angles' => ['1580489944761-15a19d654956', '1503919545889-aef636e10ad4'],
                'price' => 449,
                'mrp' => 699,
                'colors' => ['Sky', 'Mint', 'Coral', 'Mustard'],
                'sizes' => ['4-5Y', '6-7Y', '8-9Y', '10-12Y'],
                'out' => ['Coral', '6-7Y'],
            ],
            [
                'name' => 'Demo Colour Hoodie',
                'brand' => 'Urban Thread',
                'category' => "Men's Clothing",
                'subcategory' => 'Hoodies & Jackets',
                'photos' => [
                    'Red' => '1556821840-3a63f95609a7',
                    'Blue' => '1578681994506-b8f463449011',
                    'Green' => '1551028719-00167b16eac5',
                    'Purple' => '1596755094514-f87e34085b2c',
                    'Yellow' => '1483985988355-763728e1935b',
                ],
                'angles' => ['1551028719-00167b16eac5', '1596755094514-f87e34085b2c'],
                'price' => 1699,
                'mrp' => 2499,
                'colors' => ['Red', 'Blue', 'Green', 'Purple', 'Yellow'],
                'sizes' => ['S', 'M', 'L', 'XL', 'XXL'],
                'out' => ['Purple', 'L'],
            ],
        ];
    }

    public function run(): void
    {
        $vendors = VendorProfile::query()->where('is_approved', true)->orderBy('id')->get();

        if ($vendors->isEmpty()) {
            return;
        }

        $categories = Category::query()->whereNull('parent_id')->get()->keyBy('name');
        $brands = Brand::query()->get()->keyBy('name');

        foreach ($this->products() as $index => $data) {
            $parent = $categories[$data['category']] ?? null;
            $brand = $brands[$data['brand']] ?? null;

            if (! $parent || ! $brand) {
                continue;
            }

            $subcategory = $parent->children()->where('name', $data['subcategory'])->first();
            $vendor = $vendors[$index % $vendors->count()];

            $product = Product::query()->firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'vendor_id' => $vendor->id,
                    'category_id' => $parent->id,
                    'subcategory_id' => $subcategory?->id ?? null,
                    'brand_id' => $brand->id,
                    'name' => $data['name'],
                    'short_description' => "Demo item to test colour and size variants — {$data['name']}.",
                    'description' => 'A demo product created to verify colour-specific images and size availability. '.
                        'Each colour has its own uploaded photo and sizes change based on the selected colour.',
                    'specifications' => ['Material' => 'Cotton blend', 'Fit' => 'Regular', 'Care' => 'Machine wash cold'],
                    'tax_class' => 'standard',
                    'gst' => 5,
                    'hsn_code' => '6109',
                    'price' => $data['price'],
                    'mrp' => $data['mrp'],
                    'stock' => 100,
                    'reserved_stock' => 0,
                    'sku' => 'DEMO-'.$index.'-'.strtoupper(Str::random(6)),
                    'images' => array_merge(
                        [$this->image((string) reset($data['photos']))],
                        array_map([$this, 'image'], $data['angles']),
                    ),
                    'attributes' => [],
                    'is_active' => true,
                    'status' => 'PUBLISHED',
                    'approval_status' => 'APPROVED',
                    'approved_at' => now(),
                    'featured' => true,
                    'best_seller' => true,
                    'is_returnable' => true,
                    'return_days' => 15,
                    'return_policy' => 'Return this item within 15 days of delivery for a full refund. Items must be unused and in original packaging.',
                    'created_by' => null,
                ],
            );

            $this->seedVariants($product, $data['photos'], $data['angles'], $data['sizes'], $data['out'] ?? null);
        }
    }

    /**
     * @param  array<string, string>  $photos  Colour name => primary Unsplash photo id.
     * @param  array<int, string>  $angles  Photo ids for extra angles shown for every colour.
     * @param  array<int, string>  $sizes
     * @param  array{string, string}|null  $outOfStock
     */
    private function seedVariants(Product $product, array $photos, array $angles, array $sizes, ?array $outOfStock): void
    {
        foreach ($photos as $color => $photoId) {
            $colorImages = array_merge(
                [$this->image($photoId)],
                array_map([$this, 'image'], $angles),
            );

            foreach ($sizes as $size) {
                $attributes = ['Color' => $color, 'Size' => $size];
                $isOut = $outOfStock === [$color, $size];

                ProductVariant::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'combination_hash' => $this->combinationHash($attributes),
                    ],
                    [
                        'sku' => strtoupper(($product->sku ?: 'DEMO').'-'.Str::slug($color).'-'.preg_replace('/[^A-Za-z0-9]/', '', $size)),
                        'price' => $product->price,
                        'mrp' => $product->mrp ?? $product->price,
                        'stock' => $isOut ? 0 : 24,
                        'reserved_stock' => 0,
                        'status' => 'ACTIVE',
                        'attributes' => $attributes,
                        'images' => $colorImages,
                    ],
                );
            }
        }
    }

    private function image(string $id): string
    {
        return "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=900&q=80";
    }

    /** @param  array<string, string>  $attributes */
    private function combinationHash(array $attributes): string
    {
        ksort($attributes);

        return sha1((string) json_encode($attributes));
    }
}
