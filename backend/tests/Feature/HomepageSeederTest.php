<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\VendorProfile;
use Database\Seeders\HomepageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_featured_categories_seed_ships_card_art_for_every_card(): void
    {
        Category::factory()->create(['name' => "Men's Dhoti Kurta", 'is_active' => true]);
        Category::factory()->create(['name' => 'Instant Saree', 'is_active' => true]);

        $this->seed(HomepageSeeder::class);

        $section = HomepageSection::query()->where('type', 'FEATURED_CATEGORIES')->firstOrFail();
        $items = $section->config['items'] ?? [];

        $this->assertCount(2, $items);

        foreach ($items as $item) {
            $this->assertNotEmpty($item['image']);
            $this->assertFileExists(public_path($item['image']));
        }
    }

    public function test_seeded_featured_categories_render_through_the_home_api(): void
    {
        Category::factory()->create(['name' => "Men's Dhoti Kurta", 'is_active' => true]);
        Category::factory()->create(['name' => 'Instant Saree', 'is_active' => true]);

        $this->seed(HomepageSeeder::class);

        $sections = collect($this->getJson('/api/v1/home')->json('data.sections'));
        $featured = $sections->firstWhere('type', 'FEATURED_CATEGORIES');

        $this->assertNotNull($featured);
        $this->assertCount(2, $featured['content']);
        $this->assertSame('/demo/instagram/ig-7.jpg', $featured['content'][0]['image']);
    }

    public function test_featured_categories_seed_is_empty_without_matching_categories(): void
    {
        $this->seed(HomepageSeeder::class);

        $section = HomepageSection::query()->where('type', 'FEATURED_CATEGORIES')->firstOrFail();

        $this->assertSame([], $section->config['items']);
    }

    public function test_demo_icon_info_and_visit_stores_ship_copy(): void
    {
        $this->seed(HomepageSeeder::class);

        $iconInfo = HomepageSection::query()->where('type', 'ICON_INFO')->firstOrFail();
        $this->assertCount(2, $iconInfo->config['items']);
        $this->assertSame('/contact', $iconInfo->config['items'][0]['url']);

        $visitStores = HomepageSection::query()->where('type', 'VISIT_STORES')->firstOrFail();
        $this->assertSame('Visit us or reach out', $visitStores->config['title']);
        $this->assertSame('/contact', $visitStores->config['button_url']);
    }

    public function test_demo_store_locations_ship_cards_built_from_vendors(): void
    {
        $vendor = VendorProfile::factory()->create([
            'store_name' => 'Store 1',
            'logo' => 'https://example.com/store-1.jpg',
        ]);

        $this->seed(HomepageSeeder::class);

        $section = HomepageSection::query()->where('type', 'STORE_LOCATIONS')->firstOrFail();

        $this->assertSame('Visit our Stores', $section->config['heading']);

        $items = $section->config['items'];
        $this->assertCount(1, $items);
        $this->assertSame('Store 1', $items[0]['name']);
        $this->assertSame('https://example.com/store-1.jpg', $items[0]['image']);
        $this->assertSame('Visit Store', $items[0]['button_text']);
        $this->assertSame('/vendors/'.$vendor->id, $items[0]['button_url']);
    }

    public function test_demo_store_locations_are_empty_without_vendors(): void
    {
        $this->seed(HomepageSeeder::class);

        $section = HomepageSection::query()->where('type', 'STORE_LOCATIONS')->firstOrFail();

        $this->assertSame([], $section->config['items']);
    }

    public function test_demo_store_locations_ship_every_approved_vendor_for_the_slider_row(): void
    {
        VendorProfile::factory()->count(6)->create();

        $this->seed(HomepageSeeder::class);

        $section = HomepageSection::query()->where('type', 'STORE_LOCATIONS')->firstOrFail();

        $this->assertCount(6, $section->config['items']);
    }

    public function test_demo_personalization_strip_ships_copy_and_a_link(): void
    {
        $this->seed(HomepageSeeder::class);

        $section = HomepageSection::query()->where('type', 'PERSONALIZATION')->firstOrFail();

        $this->assertSame('Curated pieces, styled to your taste', $section->title);
        $this->assertSame('View Your Edit', $section->config['button_text']);
        $this->assertSame('/featured-products', $section->config['button_url']);
    }
}
