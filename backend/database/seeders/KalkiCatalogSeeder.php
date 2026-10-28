<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection as ProductCollection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VendorProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Rebuilds the storefront catalogue to mirror KALKI Fashion's taxonomy and
 * merchandising style (bridal & occasion wear). Product copy, brands and
 * imagery are our own / licensed stock — no third-party catalogue data is
 * copied.
 */
class KalkiCatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Top-level category => child categories (sub-categories).
     *
     * @var array<string, array<int, string>>
     */
    private array $tree = [
        'Lehenga' => ['Bridal Lehenga', 'Engagement Lehenga', 'Mehendi Lehenga', 'Haldi Lehenga', 'Floral Lehenga', 'Mirror Lehenga', 'Jacket Lehenga', 'Crop Top Lehenga'],
        'Saree' => ['Bridal Saree', 'Embroidered Saree', 'Kanjivaram Saree', 'Bandhani Saree', 'Festive Saree', 'Party Saree', 'Saree Blouse', 'Instant Saree'],
        'Salwar Suit' => ['Anarkali Suit', 'Chikankari Suit', 'Bandhani Suit', 'Punjabi Suit', 'Palazzo Suit', 'Sharara Suit', 'Gharara', 'Pant Suit'],
        'Kurta & Kurti' => ['Kurta Set', 'Kurta Jacket Set', 'Dhoti Kurta', 'Kurtis', 'Co-ord Kurta Sets', 'Kurta Jumpsuit', 'Tunic Tops'],
        'Gown' => ['Bridal Gown', 'Cocktail Gown', 'Party Gown', 'Indo-Western Gown', 'Wedding Gown'],
        'Indo Western' => ['Cape Style', 'Dhoti Suit', 'Crop Top Pant Set', 'Crop Top Skirt Set', 'Fusion Jumpsuit'],
        "Men's Wear" => ['Sherwani', "Men's Kurta Set", 'Jodhpuri Suit', "Men's Indo Western", "Men's Shirts", "Men's Kurta Jacket", 'Nehru Jacket', "Men's Dhoti Kurta"],
        'Kids' => ['Boys', 'Girls', 'Babies', 'Kids Party Wear'],
        'Jewellery & Accessories' => ['Earrings', 'Kundan Jewellery', 'Handbag', 'Clutch', 'Footwear', 'Dupatta', 'Head Accessories'],
        'Bridal' => ['Bridal Couture Lehenga', 'Bridal Couture Saree', 'Bridal Gown Couture', 'Bridesmaid', "Bride's Mother", 'Groom Wear', 'Engagement Wear', 'Mehendi Wear'],
    ];

    /**
     * Colour + size palette per top-level category.
     *
     * @var array<string, array{colors: array<int, string>, sizes: array<int, string>}>
     */
    private array $palettes = [
        'Lehenga' => ['colors' => ['Rani Pink', 'Ivory', 'Maroon', 'Royal Blue', 'Emerald', 'Peach', 'Wine'], 'sizes' => ['XS', 'S', 'M', 'L', 'XL']],
        'Saree' => ['colors' => ['Crimson', 'Teal', 'Navy', 'Mustard', 'Emerald', 'Magenta', 'Ivory'], 'sizes' => ['XS', 'S', 'M', 'L', 'XL']],
        'Salwar Suit' => ['colors' => ['Mint', 'Powder Blue', 'Peach', 'Maroon', 'Grey', 'Mustard'], 'sizes' => ['XS', 'S', 'M', 'L', 'XL', 'XXL']],
        'Kurta & Kurti' => ['colors' => ['White', 'Indigo', 'Olive', 'Rust', 'Black', 'Beige'], 'sizes' => ['XS', 'S', 'M', 'L', 'XL', 'XXL']],
        'Gown' => ['colors' => ['Champagne', 'Wine', 'Navy', 'Emerald', 'Rose Gold'], 'sizes' => ['XS', 'S', 'M', 'L', 'XL']],
        'Indo Western' => ['colors' => ['Ivory', 'Black', 'Rose', 'Teal', 'Gold'], 'sizes' => ['XS', 'S', 'M', 'L', 'XL']],
        "Men's Wear" => ['colors' => ['Ivory', 'Maroon', 'Navy', 'Black', 'Gold', 'Beige'], 'sizes' => ['38', '40', '42', '44', '46']],
        'Kids' => ['colors' => ['Pink', 'Yellow', 'Mint', 'Sky', 'Red', 'Cream'], 'sizes' => ['2-3Y', '4-5Y', '6-7Y', '8-9Y', '10-12Y']],
        'Jewellery & Accessories' => ['colors' => ['Gold', 'Silver', 'Rose Gold', 'Pearl'], 'sizes' => ['Free Size']],
        'Bridal' => ['colors' => ['Red', 'Maroon', 'Gold', 'Ivory', 'Wine'], 'sizes' => ['XS', 'S', 'M', 'L', 'XL']],
    ];

    /**
     * Unsplash photo-id pools (licence: Unsplash, free use) per apparel group.
     *
     * @var array<string, array<int, string>>
     */
    private array $pools = [
        'women' => [
            '1583391733956-3750e0ff4e8b', '1595777457583-95e059d581b8', '1544022613-e87ca75a784a',
            '1583496661160-fb5886a0aaaa', '1515372039744-b8f02a3ae446', '1596755094514-f87e34085b2c',
            '1483985988355-763728e1935b', '1576566588028-4147f3842f27', '1584362917165-526a968579e8',
            '1519238263530-99bdd11df2ea', '1522771739844-6a9f6d5f14af', '1490578474895-699cd4e2cf59',
            '1555252333-9f8e92e65df9', '1556228578-8c89e6adf883', '1521572163474-6864f9cf17ab',
            '1445205170230-053b83016050', '1496181133206-80ce9b88a853', '1441986300917-64674bd600d8',
            '1445527815219-ecbfec67492e', '1532298229144-0ec0c57515c7',
        ],
        'men' => [
            '1617137968427-85924c800a22', '1598033129183-c4f50c736f10', '1594938298603-c8148c4dae35',
            '1484704849700-f032a568e944', '1551028719-00167b16eac5', '1542272604-787c3835535d',
            '1596755094514-f87e34085b2c', '1596462502278-27bfdc403348',
        ],
        'kids' => [
            '1503919545889-aef636e10ad4', '1471286174890-9c112ffca5b4', '1519238263530-99bdd11df2ea',
            '1544022613-e87ca75a784a', '1483985988355-763728e1935b', '1555252333-9f8e92e65df9',
        ],
        'jewellery' => [
            '1596462502278-27bfdc403348', '1612036782180-6f0b6cd846fe', '1532298229144-0ec0c57515c7',
            '1556228578-8c89e6adf883', '1522337660859-02fbefca4702', '1576566588028-4147f3842f27',
        ],
    ];

    /** @var array<string, string> */
    private array $categoryImages = [
        'Lehenga' => '1595777457583-95e059d581b8',
        'Saree' => '1483985988355-763728e1935b',
        'Salwar Suit' => '1555252333-9f8e92e65df9',
        'Kurta & Kurti' => '1583391733956-3750e0ff4e8b',
        'Gown' => '1522771739844-6a9f6d5f14af',
        'Indo Western' => '1576566588028-4147f3842f27',
        "Men's Wear" => '1617137968427-85924c800a22',
        'Kids' => '1503919545889-aef636e10ad4',
        'Jewellery & Accessories' => '1596462502278-27bfdc403348',
        'Bridal' => '1583391733956-3750e0ff4e8b',
    ];

    /**
     * Signature pieces (curated): top-level => list of [name, sub, price, mrp].
     *
     * @var array<string, array<int, array{0: string, 1: string, 2: int, 3: int}>>
     */
    private array $catalog = [
        'Lehenga' => [
            ['Rani Pink Zardozi Bridal Lehenga', 'Bridal Lehenga', 68900, 92000],
            ['Ivory Pearl Embroidered Lehenga', 'Engagement Lehenga', 45500, 62000],
            ['Maroon Velvet Bridal Lehenga', 'Bridal Lehenga', 82000, 110000],
            ['Mint Mirror Work Lehenga', 'Mehendi Lehenga', 38900, 52000],
            ['Yellow Floral Sequin Lehenga', 'Haldi Lehenga', 29900, 39900],
            ['Pastel Floral Organza Lehenga', 'Floral Lehenga', 34900, 46000],
            ['Royal Blue Jacket Style Lehenga', 'Jacket Lehenga', 52000, 68000],
            ['Peach Crop Top Lehenga Set', 'Crop Top Lehenga', 27500, 36000],
        ],
        'Saree' => [
            ['Crimson Gold Bridal Saree', 'Bridal Saree', 42000, 55000],
            ['Emerald Sequin Embroidered Saree', 'Embroidered Saree', 24500, 32000],
            ['Scarlet Kanjivaram Silk Saree', 'Kanjivaram Saree', 38000, 49000],
            ['Red Bandhani Gharchola Saree', 'Bandhani Saree', 18500, 24000],
            ['Mustard Printed Festive Saree', 'Festive Saree', 9900, 13900],
            ['Navy Shimmer Party Saree', 'Party Saree', 15500, 21000],
            ['Ivory Pearl Saree Blouse', 'Saree Blouse', 7900, 11000],
            ['Wine Ready-to-Wear Saree', 'Instant Saree', 12900, 17500],
        ],
        'Salwar Suit' => [
            ['Powder Blue Anarkali Suit', 'Anarkali Suit', 22500, 30000],
            ['Ivory Chikankari Suit', 'Chikankari Suit', 16500, 22000],
            ['Maroon Bandhani Suit', 'Bandhani Suit', 14500, 19500],
            ['Mint Punjabi Patiala Suit', 'Punjabi Suit', 12500, 17000],
            ['Peach Palazzo Suit Set', 'Palazzo Suit', 9900, 13500],
            ['Grey Sharara Suit Set', 'Sharara Suit', 18500, 24500],
            ['Mustard Gharara Set', 'Gharara', 19900, 26000],
            ['Olive Pant Suit Set', 'Pant Suit', 8900, 12000],
        ],
        'Kurta & Kurti' => [
            ['White Chanderi Kurta Set', 'Kurta Set', 7900, 10500],
            ['Indigo Kurta Jacket Set', 'Kurta Jacket Set', 11500, 15000],
            ['Rust Dhoti Kurta Set', 'Dhoti Kurta', 8900, 12000],
            ['Olive Printed Kurti', 'Kurtis', 2900, 4200],
            ['Beige Co-ord Kurta Set', 'Co-ord Kurta Sets', 6900, 9200],
            ['Black Kurta Jumpsuit', 'Kurta Jumpsuit', 5900, 8200],
            ['Mustard Tunic Top', 'Tunic Tops', 2499, 3600],
        ],
        'Gown' => [
            ['Champagne Sequin Bridal Gown', 'Bridal Gown', 42000, 56000],
            ['Wine Cocktail Gown', 'Cocktail Gown', 19900, 27000],
            ['Navy Shimmer Party Gown', 'Party Gown', 15500, 21000],
            ['Emerald Indo-Western Gown', 'Indo-Western Gown', 26500, 34000],
            ['Ivory Ruffled Wedding Gown', 'Wedding Gown', 32900, 44000],
        ],
        'Indo Western' => [
            ['Ivory Cape Style Set', 'Cape Style', 24500, 32000],
            ['Black Dhoti Suit Set', 'Dhoti Suit', 19900, 26000],
            ['Rose Crop Top Pant Set', 'Crop Top Pant Set', 15500, 20500],
            ['Teal Crop Top Skirt Set', 'Crop Top Skirt Set', 17900, 23500],
            ['Gold Fusion Jumpsuit', 'Fusion Jumpsuit', 13900, 18500],
        ],
        "Men's Wear" => [
            ['Ivory Zari Sherwani Set', 'Sherwani', 48000, 64000],
            ['Maroon Velvet Men\'s Kurta Set', 'Men\'s Kurta Set', 18500, 25000],
            ['Navy Jodhpuri Suit', 'Jodhpuri Suit', 26500, 35000],
            ['Black Men\'s Indo Western', 'Men\'s Indo Western', 22900, 30000],
            ['White Linen Men\'s Shirt', 'Men\'s Shirts', 3499, 4999],
            ['Gold Men\'s Kurta Jacket', 'Men\'s Kurta Jacket', 15900, 21000],
            ['Beige Nehru Jacket', 'Nehru Jacket', 6900, 9500],
            ['Rust Men\'s Dhoti Kurta', 'Men\'s Dhoti Kurta', 9900, 13500],
        ],
        'Kids' => [
            ['Boys Mint Kurta Pyjama Set', 'Boys', 3499, 4999],
            ['Girls Pink Lehenga Choli', 'Girls', 4900, 6800],
            ['Baby Cream Silk Dhoti Set', 'Babies', 2900, 3900],
            ['Kids Party Wear Gown', 'Kids Party Wear', 5900, 8200],
            ['Boys Ivory Sherwani Set', 'Boys', 6900, 9200],
            ['Girls Yellow Sharara Set', 'Girls', 4500, 6200],
        ],
        'Jewellery & Accessories' => [
            ['Kundan Chandelier Earrings', 'Earrings', 4900, 7000],
            ['Polki Kundan Necklace Set', 'Kundan Jewellery', 18500, 26000],
            ['Gold Embroidered Potli Handbag', 'Handbag', 5900, 8200],
            ['Pearl Beaded Clutch', 'Clutch', 3499, 4900],
            ['Zari Embroidered Juttis', 'Footwear', 2900, 4200],
            ['Banarasi Silk Dupatta', 'Dupatta', 3900, 5400],
            ['Maang Tikka With Earrings', 'Head Accessories', 2499, 3600],
        ],
        'Bridal' => [
            ['Red Bridal Couture Lehenga', 'Bridal Couture Lehenga', 95000, 130000],
            ['Maroon Bridal Couture Saree', 'Bridal Couture Saree', 62000, 82000],
            ['Champagne Bridal Gown Couture', 'Bridal Gown Couture', 78000, 105000],
            ['Peach Bridesmaid Lehenga', 'Bridesmaid', 32500, 44000],
            ['Wine Bride\'s Mother Saree', 'Bride\'s Mother', 28500, 38000],
            ['Ivory Groom Sherwani', 'Groom Wear', 55000, 72000],
            ['Powder Blue Engagement Lehenga', 'Engagement Wear', 41500, 56000],
            ['Mint Mehendi Sharara Set', 'Mehendi Wear', 24900, 33000],
        ],
    ];

    /**
     * Embellishment / style vocabulary per apparel group for generated names.
     *
     * @var array<string, array<int, string>>
     */
    private array $embellishments = [
        'women' => ['Zardozi', 'Sequin', 'Mirror Work', 'Pearl Embroidered', 'Thread Embroidered', 'Floral', 'Bandhani', 'Chikankari', 'Gota Patti', 'Resham', 'Velvet', 'Organza', 'Chanderi', 'Georgette', 'Shimmer', 'Printed', 'Layered', 'Panelled', 'Handloom', 'Kundan'],
        'men' => ['Zari', 'Velvet', 'Embroidered', 'Brocade', 'Jacquard', 'Linen', 'Silk', 'Textured', 'Chikankari', 'Thread Work'],
        'kids' => ['Silk', 'Cotton', 'Embroidered', 'Printed', 'Festive', 'Zari', 'Mirror Work', 'Sequin'],
        'jewellery' => ['Kundan', 'Polki', 'Pearl', 'Meenakari', 'Zari', 'Embroidered', 'Beaded', 'Temple', 'Enamel', 'Stone'],
    ];

    /**
     * Price band (min, max) per top-level category.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    private array $priceRanges = [
        'Lehenga' => [24900, 89000],
        'Saree' => [8900, 45000],
        'Salwar Suit' => [6900, 24900],
        'Kurta & Kurti' => [2499, 11900],
        'Gown' => [14900, 44900],
        'Indo Western' => [12900, 29900],
        "Men's Wear" => [3499, 49900],
        'Kids' => [2499, 6900],
        'Jewellery & Accessories' => [1499, 19900],
        'Bridal' => [24900, 99900],
    ];

    /**
     * Readable nouns for sub-categories whose default label reads awkwardly.
     *
     * @var array<string, string>
     */
    private array $nounOverrides = [
        'Kurtis' => 'Kurti',
        'Tunic Tops' => 'Tunic Top',
        'Co-ord Kurta Sets' => 'Co-ord Kurta Set',
        'Cape Style' => 'Cape Set',
        'Boys' => 'Kurta Pyjama Set',
        'Girls' => 'Lehenga Choli Set',
        'Babies' => 'Silk Dhoti Set',
        'Kids Party Wear' => 'Party Wear Set',
        'Footwear' => 'Juttis',
        'Head Accessories' => 'Maang Tikka',
        'Kundan Jewellery' => 'Kundan Necklace Set',
        'Handbag' => 'Potli Bag',
        "Men's Shirts" => 'Shirt',
        'Bridal Couture Lehenga' => 'Couture Lehenga',
        'Bridal Couture Saree' => 'Couture Saree',
        'Bridal Gown Couture' => 'Couture Gown',
        'Bridesmaid' => 'Bridesmaid Lehenga',
        "Bride's Mother" => 'Mother-of-the-Bride Saree',
        'Groom Wear' => 'Groom Sherwani',
        'Engagement Wear' => 'Engagement Lehenga Set',
        'Mehendi Wear' => 'Mehendi Sharara Set',
    ];

    /** Target pieces per sub-category (signature + generated). */
    private int $perSubcategory = 10;

    /**
     * @var array<string, string>
     */
    private array $brands = [
        'Mehr Atelier' => 'Bengaluru couture house for bridal & occasion wear.',
        'Rangreza' => 'Hand-embroidered festive and wedding silhouettes.',
        'Zari & Co.' => 'Heritage zardozi craft, reimagined for today.',
        'Aarna Couture' => 'Contemporary Indian couture and indo-western edits.',
        'Noor Threads' => 'Chikankari, mulmul and everyday luxury.',
        'Roohi' => 'Vibrant bandhani, gharara and festive sets.',
        'Vastraa' => 'Modern drape sarees and ready-to-wear edits.',
        'Poshak' => 'Regal men\'s wedding and festive wear.',
    ];

    public function run(): void
    {
        $this->seedCategories();
        $this->seedBrands();

        $vendors = VendorProfile::query()->where('is_approved', true)->orderBy('id')->get();

        if ($vendors->isEmpty()) {
            return;
        }

        $this->seedProducts($vendors);
        $this->seedCollections();
    }

    /**
     * Curated KALKI-style storefront collections, grouped by gender and mapped
     * onto the real category tree (products keep their parent category_id).
     *
     * @var array<string, array{name: string, gender: string, categories: array<int, string>, description: string}>
     */
    private array $collections = [
        'bridal' => [
            'name' => 'Bridal Couture',
            'gender' => 'female',
            'categories' => ['Bridal'],
            'description' => 'Couture lehengas, sarees and gowns for the modern bride.',
        ],
        'wedding-lehenga' => [
            'name' => 'Wedding Lehenga',
            'gender' => 'female',
            'categories' => ['Lehenga'],
            'description' => 'Hand-embroidered lehengas for weddings and celebrations.',
        ],
        'designer-saree' => [
            'name' => 'Designer Saree',
            'gender' => 'female',
            'categories' => ['Saree'],
            'description' => 'Drapes woven with heritage craft and modern drama.',
        ],
        'festive-salwar-suits' => [
            'name' => 'Festive Salwar Suits',
            'gender' => 'female',
            'categories' => ['Salwar Suit'],
            'description' => 'Anarkalis, shararas and suit sets for the festive season.',
        ],
        'jewellery' => [
            'name' => 'Jewellery & Accessories',
            'gender' => 'female',
            'categories' => ['Jewellery & Accessories'],
            'description' => 'Kundan, polki and handcrafted accessories to finish the look.',
        ],
        'gowns' => [
            'name' => 'Occasion Gowns',
            'gender' => 'female',
            'categories' => ['Gown'],
            'description' => 'Cocktail, party and indo-western gowns with a contemporary edge.',
        ],
        'indo-western' => [
            'name' => 'Indo Western',
            'gender' => 'unisex',
            'categories' => ['Indo Western'],
            'description' => 'Cape sets, dhoti suits and fusion silhouettes.',
        ],
        'mens-wedding-wear' => [
            'name' => "Men's Wedding Wear",
            'gender' => 'male',
            'categories' => ["Men's Wear"],
            'description' => 'Sherwanis, kurta sets and bandhgalas for the groom.',
        ],
        'kids-festive' => [
            'name' => 'Kids Festive',
            'gender' => 'kids',
            'categories' => ['Kids'],
            'description' => 'Little celebrations in festive ethnic wear.',
        ],
    ];

    private function seedCollections(): void
    {
        $parents = Category::query()->whereNull('parent_id')->get()->keyBy('name');
        $sort = 0;

        foreach ($this->collections as $slug => $definition) {
            $parentIds = collect($definition['categories'])
                ->map(fn (string $name): ?int => $parents->get($name)?->id)
                ->filter()
                ->values();

            $imagePool = $this->pools[$this->groupFor($definition['categories'][0])] ?? $this->pools['women'];

            $collection = ProductCollection::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'gender' => $definition['gender'],
                    'image' => $this->image($imagePool[0]).'&w=750&h=750&fit=crop',
                    'banner_image' => $this->image($imagePool[1 % count($imagePool)]).'&w=1600&h=600&fit=crop',
                    'is_active' => true,
                    'sort_order' => $sort++,
                    'meta_title' => $definition['name'].' — Shop Online',
                    'meta_description' => $definition['description'],
                ],
            );

            $productIds = Product::query()
                ->whereIn('category_id', $parentIds)
                ->pluck('id')
                ->all();

            $collection->products()->sync($productIds);
        }
    }

    private function seedCategories(): void
    {
        $parentSort = 0;

        foreach ($this->tree as $parentName => $children) {
            $parent = Category::query()->updateOrCreate(
                ['slug' => Str::slug($parentName)],
                [
                    'name' => $parentName,
                    'parent_id' => null,
                    'description' => "Shop the {$parentName} collection — bridal, festive and occasion wear.",
                    'image' => $this->image($this->categoryImages[$parentName]).'&w=800&h=600&fit=crop',
                    'is_active' => true,
                    'status' => 'ACTIVE',
                    'sort_order' => $parentSort++,
                ],
            );

            $pool = $this->pools[$this->groupFor($parentName)];

            foreach ($children as $index => $childName) {
                Category::query()->updateOrCreate(
                    ['slug' => Str::slug($childName)],
                    [
                        'name' => $childName,
                        'parent_id' => $parent->id,
                        'description' => "{$childName} — handpicked {$parentName} edits.",
                        'image' => $this->image($pool[$index % count($pool)]).'&w=600&h=450&fit=crop',
                        'is_active' => true,
                        'status' => 'ACTIVE',
                        'sort_order' => $index,
                    ],
                );
            }
        }
    }

    private function seedBrands(): void
    {
        foreach ($this->brands as $name => $description) {
            Brand::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description,
                    'logo' => $this->image($this->pools['women'][0]).'&w=400&h=400&fit=crop',
                    'meta_title' => "{$name} — Buy Online",
                    'meta_description' => "Shop the {$name} wedding & festive collection online.",
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, VendorProfile>  $vendors
     */
    private function seedProducts($vendors): void
    {
        $brands = Brand::query()->get()->keyBy('name')->values();
        $parents = Category::query()->whereNull('parent_id')->get()->keyBy('name');

        $vendorCount = $vendors->count();
        $usedSlugs = [];
        $index = 0;

        foreach ($this->tree as $parentName => $children) {
            $parent = $parents->get($parentName);

            if ($parent === null) {
                continue;
            }

            $palette = $this->palettes[$parentName];
            $pool = $this->pools[$this->groupFor($parentName)];
            $subcategories = Category::query()
                ->where('parent_id', $parent->id)
                ->whereIn('name', $children)
                ->get()
                ->keyBy('name');

            foreach ($this->entriesFor($parentName, $children) as $offset => $entry) {
                $subcategory = $subcategories->get($entry['sub']);

                if ($subcategory === null) {
                    continue;
                }

                $slug = Str::slug($entry['name']);

                if (isset($usedSlugs[$slug])) {
                    continue;
                }

                $usedSlugs[$slug] = true;

                $product = $this->createProduct(
                    vendors: $vendors,
                    vendorIndex: $index % $vendorCount,
                    brand: $brands->get($index % $brands->count()),
                    parent: $parent,
                    parentName: $parentName,
                    subcategory: $subcategory,
                    name: $entry['name'],
                    price: $entry['price'],
                    mrp: $entry['mrp'],
                    offset: $offset,
                    imageSeed: $index,
                    pool: $pool,
                );

                $this->seedVariants($product, $palette['colors'], $palette['sizes'], $pool);

                $index++;
            }
        }
    }

    /**
     * Signature pieces plus generated pieces until every sub-category reaches
     * the target depth.
     *
     * @param  array<int, string>  $children
     * @return array<int, array{name: string, sub: string, price: int, mrp: int}>
     */
    private function entriesFor(string $parentName, array $children): array
    {
        $signature = collect($this->catalog[$parentName] ?? [])
            ->map(fn (array $row): array => ['name' => $row[0], 'sub' => $row[1], 'price' => $row[2], 'mrp' => $row[3]]);

        $group = $this->groupFor($parentName);
        $works = $this->embellishments[$group];
        $colors = $this->palettes[$parentName]['colors'];
        [$min, $max] = $this->priceRanges[$parentName];

        $used = $signature->pluck('name')->flip()->all();
        $entries = [];

        foreach ($children as $si => $child) {
            $forChild = $signature->where('sub', $child)->values();
            $entries = array_merge($entries, $forChild->all());

            $need = max(0, $this->perSubcategory - $forChild->count());

            for ($g = 0; $g < $need; $g++) {
                $noun = $this->nounFor($child);
                $name = '';

                for ($attempt = 0; $attempt < count($works); $attempt++) {
                    $color = $colors[($si * 3 + $g + $attempt) % count($colors)];
                    $work = $works[($si * 5 + $g * 2 + $attempt) % count($works)];
                    $candidate = $this->composeName($color, $work, $noun, $g);

                    if (! isset($used[$candidate])) {
                        $name = $candidate;
                        break;
                    }
                }

                if ($name === '') {
                    $base = $this->composeName($colors[$g % count($colors)], $works[$g % count($works)], $noun, $g);
                    $name = $base;
                    $suffix = 2;

                    while (isset($used[$name])) {
                        $name = "{$base} {$suffix}";
                        $suffix++;
                    }
                }

                $used[$name] = true;

                $seed = ($si + 1) * 97 + ($g + 1) * 41 + strlen($child);
                $price = (int) (round(($min + (($max - $min) * ($seed % 100)) / 100) / 100) * 100);
                $mrp = (int) (round(($price * 1.35) / 100) * 100);

                $entries[] = [
                    'name' => $name,
                    'sub' => $child,
                    'price' => $price,
                    'mrp' => max($mrp, $price + 500),
                ];
            }
        }

        return $entries;
    }

    private function composeName(string $color, string $work, string $noun, int $variant): string
    {
        return match ($variant % 3) {
            0 => trim("{$color} {$work} {$noun}"),
            1 => trim("{$work} {$noun}"),
            default => trim("{$color} {$noun}"),
        };
    }

    private function nounFor(string $child): string
    {
        return $this->nounOverrides[$child] ?? $child;
    }

    private function groupFor(string $parentName): string
    {
        return match ($parentName) {
            "Men's Wear" => 'men',
            'Kids' => 'kids',
            'Jewellery & Accessories' => 'jewellery',
            default => 'women',
        };
    }

    private function createProduct(
        $vendors,
        int $vendorIndex,
        ?Brand $brand,
        Category $parent,
        string $parentName,
        Category $subcategory,
        string $name,
        int $price,
        int $mrp,
        int $offset,
        int $imageSeed,
        array $pool,
    ): Product {
        $images = $this->imagesFor($pool, $imageSeed);

        return Product::query()->updateOrCreate(
            ['slug' => Str::slug($name)],
            [
                'vendor_id' => $vendors[$vendorIndex]->id,
                'category_id' => $parent->id,
                'subcategory_id' => $subcategory->id,
                'brand_id' => $brand?->id,
                'name' => $name,
                'short_description' => "Occasion-ready {$subcategory->name} crafted by {$brand?->name}.",
                'description' => "A signature {$subcategory->name} from our {$parentName} edit. "
                    .'Hand-finished with intricate embroidery, premium fabric and a flattering drape — '
                    .'designed for weddings, festivities and celebrations.',
                'highlights' => ['Handcrafted embroidery', 'Premium fabric', 'Dry clean only'],
                'specifications' => [
                    'Fabric' => 'Silk blend',
                    'Work' => 'Hand embroidery',
                    'Sleeve' => 'As shown',
                    'Care' => 'Dry clean only',
                ],
                'tax_class' => 'standard',
                'gst' => 5,
                'hsn_code' => '6204',
                'price' => $price,
                'mrp' => $mrp,
                'stock' => 0,
                'reserved_stock' => 0,
                'sku' => 'KLK-'.strtoupper(substr(sha1($name), 0, 7)),
                'images' => $images,
                'attributes' => [],
                'is_active' => true,
                'status' => 'PUBLISHED',
                'approval_status' => 'APPROVED',
                'approved_at' => now(),
                'featured' => $offset < 2,
                'best_seller' => $offset % 5 === 2,
                'is_returnable' => true,
                'return_days' => 7,
                'return_policy' => 'Return within 7 days of delivery, unused and with original packaging.',
                'created_by' => null,
            ],
        );
    }

    /**
     * @param  array<int, string>  $colors
     * @param  array<int, string>  $sizes
     * @param  array<int, string>  $pool
     */
    private function seedVariants(Product $product, array $colors, array $sizes, array $pool): void
    {
        $offset = $product->id % count($colors);
        $rotated = array_merge(array_slice($colors, $offset), array_slice($colors, 0, $offset));
        $chosen = array_slice($rotated, 0, min(3, count($colors)));

        // Variant images rotate through the product's own images so a colour
        // never shows another product's (or the pool's first) photos.
        $productImages = is_array($product->images) && $product->images !== []
            ? array_values($product->images)
            : $this->imagesFor($pool, $product->id);
        $imageCount = count($productImages);

        foreach ($chosen as $colorIndex => $color) {
            $colorImages = [
                $productImages[$colorIndex % $imageCount],
                $productImages[($colorIndex + 1) % $imageCount],
            ];

            foreach ($sizes as $size) {
                $attributes = ['Color' => $color, 'Size' => $size];

                ProductVariant::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'combination_hash' => sha1((string) json_encode($attributes)),
                    ],
                    [
                        'sku' => strtoupper(($product->sku ?: 'KLK-'.$product->id).'-'.Str::slug($color).'-'.preg_replace('/[^A-Za-z0-9]/', '', $size)),
                        'price' => $product->price,
                        'mrp' => $product->mrp ?? $product->price,
                        'cost_price' => round((float) $product->price * 0.6, 2),
                        'stock' => 6 + (($product->id + strlen($color) + strlen($size)) % 20),
                        'status' => 'ACTIVE',
                        'attributes' => $attributes,
                        'images' => $colorImages,
                    ],
                );
            }
        }

        $product->forceFill(['stock' => $product->variants()->sum('stock')])->save();
    }

    /**
     * Pick three distinct images from the pool for a product. The `$seed` is a
     * stable per-product value so each product gets its own combination instead
     * of the same first images repeated across the catalogue.
     *
     * @param  array<int, string>  $pool
     * @return array<int, string>
     */
    private function imagesFor(array $pool, int $seed): array
    {
        $count = count($pool);
        $chosen = [];
        $attempt = 0;

        while (count($chosen) < min(3, $count)) {
            $index = crc32($seed.'-'.$attempt) % $count;

            if (! in_array($index, $chosen, true)) {
                $chosen[] = $index;
            }

            $attempt++;
        }

        return array_map(
            fn (int $index): string => $this->image($pool[$index]).'&w=900&q=80',
            $chosen,
        );
    }

    private function image(string $id): string
    {
        return "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop";
    }
}
