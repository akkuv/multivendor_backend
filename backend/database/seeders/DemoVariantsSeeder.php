<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoVariantsSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var array<string, array<int, string>> */
    private array $colorsBySubcategory = [
        'Dresses' => ['Burgundy', 'Forest', 'Black', 'Rose', 'Teal'],
        'Tops & Blouses' => ['Black', 'White', 'Navy', 'Rose', 'Olive'],
        'Jeans & Trousers' => ['Blue', 'Black', 'Grey', 'Stone'],
        'Outerwear' => ['Black', 'Navy', 'Grey', 'Olive', 'Camel'],
        'Skirts & Shorts' => ['Black', 'Rose', 'Olive', 'Navy'],
        'T-Shirts' => ['Black', 'White', 'Navy', 'Grey', 'Olive'],
        'Shirts' => ['White', 'Blue', 'Black', 'Teal', 'Grey'],
        'Hoodies & Jackets' => ['Black', 'Navy', 'Grey', 'Olive', 'Camel'],
        'Formal Wear' => ['Black', 'Navy', 'Charcoal', 'Grey'],
    ];

    /** @var array<string, array<int, string>> */
    private array $sizesBySubcategory = [
        'Dresses' => ['XS', 'S', 'M', 'L', 'XL'],
        'Tops & Blouses' => ['S', 'M', 'L', 'XL', 'XXL'],
        'Jeans & Trousers' => ['28', '30', '32', '34', '36'],
        'Outerwear' => ['S', 'M', 'L', 'XL', 'XXL'],
        'Skirts & Shorts' => ['XS', 'S', 'M', 'L'],
        'T-Shirts' => ['S', 'M', 'L', 'XL', 'XXL'],
        'Shirts' => ['S', 'M', 'L', 'XL', 'XXL'],
        'Hoodies & Jackets' => ['S', 'M', 'L', 'XL', 'XXL'],
        'Formal Wear' => ['XS', 'S', 'M', 'L', 'XL'],
    ];

    /** @var array<string, array<int, string>> */
    private array $sizesByKidsSubcategory = [
        'Boys' => ['4-5Y', '6-7Y', '8-9Y', '10-12Y'],
        'Girls' => ['4-5Y', '6-7Y', '8-9Y', '10-12Y'],
        'Babies' => ['0-6M', '6-12M', '1-2Y'],
    ];

    /** @var array<int, string> */
    private const KIDS_COLORS = ['Sky', 'Mustard', 'Rose', 'Navy', 'Mint', 'Coral'];

    /**
     * Deterministic Unsplash photo id per colour so every colour variant shows
     * its own real photo instead of a tinted copy of the product image.
     *
     * @var array<string, string>
     */
    private const PHOTO_BY_COLOR = [
        'Black' => '1576566588028-4147f3842f27',
        'White' => '1521572163474-6864f9cf17ab',
        'Navy' => '1596755094514-f87e34085b2c',
        'Grey' => '1471286174890-9c112ffca5b4',
        'Stone' => '1550246140-29f40b909e5a',
        'Blue' => '1542272604-787c3835535d',
        'Teal' => '1584370848010-d7fe6bc767ec',
        'Olive' => '1581655353564-df123a1eb820',
        'Camel' => '1544022613-e87ca75a784a',
        'Burgundy' => '1595777457583-95e059d581b8',
        'Forest' => '1515372039744-b8f02a3ae446',
        'Rose' => '1583496661160-fb5886a0aaaa',
        'Charcoal' => '1594938298603-c8148c4dae35',
        'Sky' => '1584362917165-526a968579e8',
        'Mustard' => '1519238263530-99bdd11df2ea',
        'Mint' => '1555252333-9f8e92e65df9',
        'Coral' => '1483985988355-763728e1935b',
    ];

    /**
     * Extra angle photos appended to every colour so each variant demonstrates
     * multiple images (front, back, side) for a single colour.
     *
     * @var array<int, string>
     */
    private const DETAIL_PHOTOS = [
        '1594938298603-c8148c4dae35',
        '1583496661160-fb5886a0aaaa',
    ];

    public function run(): void
    {
        $products = Product::query()
            ->whereNotNull('subcategory_id')
            ->with('subcategory')
            ->get();

        foreach ($products as $product) {
            $subcategory = $product->subcategory;

            if (! $subcategory) {
                continue;
            }

            $subcategoryName = $subcategory->name;

            $colors = $this->colorsFor($product, $subcategoryName);
            $sizes = $this->sizesFor($product, $subcategoryName);

            if ($colors === [] || $sizes === []) {
                continue;
            }

            foreach ($colors as $color) {
                $colorImages = $this->imagesForColor($product, $color);

                foreach ($sizes as $size) {
                    $attributes = ['Color' => $color, 'Size' => $size];

                    ProductVariant::query()->updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'combination_hash' => $this->combinationHash($attributes),
                        ],
                        [
                            'sku' => $this->variantSku($product, $color, $size),
                            'price' => $product->price,
                            'mrp' => $product->mrp ?? $product->price,
                            'cost_price' => $product->mrp ? round($product->price * 0.7, 2) : null,
                            'stock' => $this->deterministicStock($product, $color, $size),
                            'status' => 'ACTIVE',
                            'attributes' => $attributes,
                            'images' => $colorImages,
                        ],
                    );
                }
            }
        }
    }

    /** @return array<int, string> */
    private function colorsFor(Product $product, string $subcategory): array
    {
        $palette = $product->category?->slug === 'kids-clothing'
            ? self::KIDS_COLORS
            : ($this->colorsBySubcategory[$subcategory] ?? []);

        if ($palette === []) {
            return [];
        }

        $offset = $product->id % count($palette);

        return array_values(array_slice(array_merge(array_slice($palette, $offset), array_slice($palette, 0, $offset)), 0, 3));
    }

    /** @return array<int, string> */
    private function sizesFor(Product $product, string $subcategory): array
    {
        if ($product->category?->slug === 'kids-clothing') {
            return $this->sizesByKidsSubcategory[$subcategory] ?? [];
        }

        return $this->sizesBySubcategory[$subcategory] ?? [];
    }

    private function variantSku(Product $product, string $color, string $size): string
    {
        $base = $product->sku ?: 'APP-'.$product->id.'-'.$product->slug;

        return strtoupper($base.'-'.Str::slug($color).'-'.preg_replace('/[^A-Za-z0-9]/', '', $size));
    }

    private function deterministicStock(Product $product, string $color, string $size): int
    {
        return 8 + (($product->id + strlen($color) + strlen($size)) % 23);
    }

    /**
     * Return the colour-specific photo for a variant so the storefront shows a
     * real image per colour. Falls back to the product's own images when the
     * colour has no dedicated demo photo.
     *
     * @return array<int, string>
     */
    private function imagesForColor(Product $product, string $color): array
    {
        $photoId = self::PHOTO_BY_COLOR[$color] ?? null;

        if ($photoId === null) {
            return (array) $product->images;
        }

        return array_merge(
            [$this->image($photoId)],
            array_map([$this, 'image'], self::DETAIL_PHOTOS),
        );
    }

    private function image(string $id): string
    {
        return "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=900&q=80";
    }

    private function combinationHash(array $attributes): string
    {
        ksort($attributes);

        return sha1((string) json_encode($attributes));
    }
}
