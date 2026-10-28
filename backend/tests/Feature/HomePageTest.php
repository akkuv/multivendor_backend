<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\Product;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_editors_picks_section_returns_configured_banner_items(): void
    {
        HomepageSection::factory()->ofType('EDITORS_PICKS')->create([
            'sort_order' => 1,
            'config' => [
                'items' => [
                    ['image' => '/uploads/one.jpg', 'title' => 'Blouse Edit', 'url' => '/collections/blouse'],
                    ['image' => '/uploads/two.jpg', 'title' => '', 'url' => '/collections/dress-material'],
                ],
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonCount(2, 'data.sections.0.content')
            ->assertJsonPath('data.sections.0.type', 'EDITORS_PICKS')
            ->assertJsonPath('data.sections.0.content.0.image', '/uploads/one.jpg')
            ->assertJsonPath('data.sections.0.content.0.title', 'Blouse Edit')
            ->assertJsonPath('data.sections.0.content.0.url', '/collections/blouse')
            ->assertJsonPath('data.sections.0.content.1.url', '/collections/dress-material');
    }

    public function test_editors_picks_section_falls_back_to_category_banners(): void
    {
        HomepageSection::factory()->ofType('EDITORS_PICKS')->create(['sort_order' => 1]);
        Category::factory()->create([
            'name' => 'Luxe',
            'slug' => 'luxe',
            'is_active' => true,
            'image' => '/uploads/luxe.jpg',
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonCount(1, 'data.sections.0.content')
            ->assertJsonPath('data.sections.0.content.0.title', 'Luxe')
            ->assertJsonPath('data.sections.0.content.0.image', '/uploads/luxe.jpg')
            ->assertJsonPath('data.sections.0.content.0.url', '/category/luxe');
    }

    public function test_featured_categories_section_returns_editor_defined_cards_with_overrides(): void
    {
        $dhoti = Category::factory()->create([
            'name' => "Men's Dhoti Kurta",
            'slug' => 'mens-dhoti-kurta',
            'is_active' => true,
            'image' => '/uploads/dhoti.jpg',
        ]);

        HomepageSection::factory()->ofType('FEATURED_CATEGORIES')->create([
            'sort_order' => 1,
            'config' => [
                'items' => [
                    [
                        'category_id' => $dhoti->id,
                        'image' => '/uploads/custom-dhoti.jpg',
                        'title' => 'Festive Edit',
                        'subtitle' => 'Shop the festive drop',
                        'url' => '/collections/festive',
                    ],
                ],
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonCount(1, 'data.sections.0.content')
            ->assertJsonPath('data.sections.0.type', 'FEATURED_CATEGORIES')
            ->assertJsonPath('data.sections.0.content.0.name', "Men's Dhoti Kurta")
            ->assertJsonPath('data.sections.0.content.0.image', '/uploads/custom-dhoti.jpg')
            ->assertJsonPath('data.sections.0.content.0.title', 'Festive Edit')
            ->assertJsonPath('data.sections.0.content.0.subtitle', 'Shop the festive drop')
            ->assertJsonPath('data.sections.0.content.0.url', '/collections/festive');
    }

    public function test_featured_categories_section_skips_cards_without_an_uploaded_image(): void
    {
        $withImage = Category::factory()->create([
            'name' => 'With Image',
            'slug' => 'with-image',
            'is_active' => true,
            'image' => '/uploads/category-image.jpg',
        ]);
        $withoutImage = Category::factory()->create([
            'name' => 'Without Image',
            'slug' => 'without-image',
            'is_active' => true,
            'image' => '/uploads/category-image.jpg',
        ]);

        HomepageSection::factory()->ofType('FEATURED_CATEGORIES')->create([
            'sort_order' => 1,
            'config' => [
                'category_ids' => [$withImage->id, $withoutImage->id],
                'items' => [
                    ['category_id' => $withImage->id, 'image' => '/uploads/uploaded.jpg'],
                    ['category_id' => $withoutImage->id, 'image' => null],
                ],
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonCount(1, 'data.sections.0.content')
            ->assertJsonPath('data.sections.0.content.0.slug', 'with-image')
            ->assertJsonPath('data.sections.0.content.0.image', '/uploads/uploaded.jpg');
    }

    public function test_featured_categories_section_renders_cards_without_a_category(): void
    {
        HomepageSection::factory()->ofType('FEATURED_CATEGORIES')->create([
            'sort_order' => 1,
            'config' => [
                'items' => [
                    [
                        'category_id' => null,
                        'image' => '/uploads/free-standing.jpg',
                        'title' => 'Festive Edit',
                        'subtitle' => 'Shop the drop',
                        'url' => '/collections/festive',
                    ],
                ],
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonCount(1, 'data.sections.0.content')
            ->assertJsonPath('data.sections.0.content.0.id', null)
            ->assertJsonPath('data.sections.0.content.0.slug', null)
            ->assertJsonPath('data.sections.0.content.0.name', 'Festive Edit')
            ->assertJsonPath('data.sections.0.content.0.image', '/uploads/free-standing.jpg')
            ->assertJsonPath('data.sections.0.content.0.title', 'Festive Edit')
            ->assertJsonPath('data.sections.0.content.0.url', '/collections/festive');
    }

    public function test_featured_categories_section_is_empty_without_editor_cards(): void
    {
        Category::factory()->create([
            'name' => 'Luxe',
            'slug' => 'luxe',
            'is_active' => true,
            'image' => '/uploads/luxe.jpg',
        ]);

        HomepageSection::factory()->ofType('FEATURED_CATEGORIES')->create([
            'sort_order' => 1,
            'config' => ['category_ids' => [1, 2, 3]],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'FEATURED_CATEGORIES')
            ->assertJsonCount(0, 'data.sections.0.content');
    }

    public function test_featured_categories_section_prefers_editor_cards_over_category_ids(): void
    {
        $first = Category::factory()->create([
            'name' => 'First',
            'slug' => 'first',
            'is_active' => true,
        ]);
        $second = Category::factory()->create([
            'name' => 'Second',
            'slug' => 'second',
            'is_active' => true,
        ]);

        HomepageSection::factory()->ofType('FEATURED_CATEGORIES')->create([
            'sort_order' => 1,
            'config' => [
                'category_ids' => [$first->id],
                'items' => [['category_id' => $second->id, 'image' => '/uploads/second.jpg', 'title' => 'Second Card']],
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonCount(1, 'data.sections.0.content')
            ->assertJsonPath('data.sections.0.content.0.slug', 'second')
            ->assertJsonPath('data.sections.0.content.0.title', 'Second Card');
    }

    public function test_home_returns_hero_banners_only_when_active_and_within_date_range(): void
    {
        HomepageSection::factory()->ofType('HERO')->create(['sort_order' => 1]);
        Banner::factory()->hero()->create(['title' => 'Visible', 'is_active' => true]);
        Banner::factory()->hero()->create(['title' => 'Inactive', 'is_active' => false]);
        Banner::factory()->hero()->create([
            'title' => 'Expired',
            'is_active' => true,
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->subDays(10)->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonCount(1, 'data.sections')
            ->assertJsonPath('data.sections.0.type', 'HERO')
            ->assertJsonCount(1, 'data.sections.0.content')
            ->assertJsonPath('data.sections.0.content.0.title', 'Visible');
    }

    public function test_home_orders_sections_by_sort_order(): void
    {
        HomepageSection::factory()->ofType('CATEGORIES')->create(['sort_order' => 2]);
        HomepageSection::factory()->ofType('HERO')->create(['sort_order' => 1]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'HERO')
            ->assertJsonPath('data.sections.1.type', 'CATEGORIES');
    }

    public function test_home_excludes_inactive_sections(): void
    {
        HomepageSection::factory()->ofType('CATEGORIES')->create(['sort_order' => 1, 'is_active' => true]);
        HomepageSection::factory()->ofType('HERO')->create(['sort_order' => 2, 'is_active' => false]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()->assertJsonCount(1, 'data.sections');
    }

    public function test_categories_section_without_configured_tiles_renders_nothing(): void
    {
        HomepageSection::factory()->ofType('CATEGORIES')->create(['sort_order' => 1]);
        Category::factory()->create(['name' => 'Top Active', 'is_active' => true]);

        $response = $this->getJson('/api/v1/home');

        // No editor-configured tiles means no auto-injected default category list.
        $response->assertOk()
            ->assertJsonCount(1, 'data.sections')
            ->assertJsonCount(0, 'data.sections.0.content');
    }

    public function test_categories_section_returns_explicitly_selected_subcategories_in_order(): void
    {
        $parent = Category::factory()->create(['name' => 'Parent', 'is_active' => true]);
        $child = Category::factory()->create(['name' => 'Child', 'is_active' => true, 'parent_id' => $parent->id]);
        $sibling = Category::factory()->create(['name' => 'Sibling', 'is_active' => true, 'parent_id' => $parent->id]);

        HomepageSection::factory()->ofType('CATEGORIES')->create([
            'sort_order' => 1,
            'config' => ['category_ids' => [$child->id, $sibling->id]],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonCount(2, 'data.sections.0.content')
            ->assertJsonPath('data.sections.0.content.0.id', $child->id)
            ->assertJsonPath('data.sections.0.content.0.name', 'Child')
            ->assertJsonPath('data.sections.0.content.1.id', $sibling->id)
            ->assertJsonPath('data.sections.0.content.1.name', 'Sibling');
    }

    public function test_categories_section_excludes_inactive_explicitly_selected_categories(): void
    {
        $active = Category::factory()->create(['name' => 'Active Pick', 'is_active' => true]);
        $inactive = Category::factory()->create(['name' => 'Inactive Pick', 'is_active' => false]);

        HomepageSection::factory()->ofType('CATEGORIES')->create([
            'sort_order' => 1,
            'config' => ['category_ids' => [$inactive->id, $active->id]],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonCount(1, 'data.sections.0.content')
            ->assertJsonPath('data.sections.0.content.0.name', 'Active Pick');
    }

    public function test_categories_section_renders_editor_defined_tiles(): void
    {
        HomepageSection::factory()->ofType('CATEGORIES')->create([
            'sort_order' => 1,
            'config' => [
                'items' => [
                    [
                        'category_id' => null,
                        'image' => '/uploads/lehenga.jpg',
                        'title' => 'Lehenga',
                        'description' => 'Hand-embroidered occasion wear',
                        'url' => null,
                    ],
                    [
                        'category_id' => null,
                        'image' => null,
                        'title' => 'Saree',
                        'description' => 'Draped elegance',
                        'url' => '/collections/saree',
                    ],
                ],
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonCount(2, 'data.sections.0.content')
            ->assertJsonPath('data.sections.0.content.0.id', null)
            ->assertJsonPath('data.sections.0.content.0.name', 'Lehenga')
            ->assertJsonPath('data.sections.0.content.0.image', '/uploads/lehenga.jpg')
            ->assertJsonPath('data.sections.0.content.0.title', 'Lehenga')
            ->assertJsonPath('data.sections.0.content.0.description', 'Hand-embroidered occasion wear')
            ->assertJsonPath('data.sections.0.content.0.url', null)
            ->assertJsonPath('data.sections.0.content.1.name', 'Saree')
            ->assertJsonPath('data.sections.0.content.1.url', '/collections/saree');
    }

    public function test_categories_section_tile_linked_to_a_category_uses_its_link_and_count(): void
    {
        $category = Category::factory()->create([
            'name' => 'Jewellery',
            'slug' => 'jewellery',
            'is_active' => true,
        ]);

        HomepageSection::factory()->ofType('CATEGORIES')->create([
            'sort_order' => 1,
            'config' => [
                'items' => [
                    ['category_id' => $category->id, 'image' => '/uploads/jewellery.jpg'],
                ],
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonCount(1, 'data.sections.0.content')
            ->assertJsonPath('data.sections.0.content.0.id', $category->id)
            ->assertJsonPath('data.sections.0.content.0.name', 'Jewellery')
            ->assertJsonPath('data.sections.0.content.0.slug', 'jewellery')
            ->assertJsonPath('data.sections.0.content.0.url', '/category/jewellery');
    }

    public function test_categories_section_skips_tiles_without_image_or_title_and_prefers_tiles_over_category_ids(): void
    {
        $picked = Category::factory()->create(['name' => 'Picked', 'slug' => 'picked', 'is_active' => true]);

        HomepageSection::factory()->ofType('CATEGORIES')->create([
            'sort_order' => 1,
            'config' => [
                'category_ids' => [$picked->id],
                'items' => [
                    ['description' => 'no art, no heading'],
                    ['image' => '/uploads/ok.jpg', 'title' => 'Only Tile'],
                ],
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonCount(1, 'data.sections.0.content')
            ->assertJsonPath('data.sections.0.content.0.name', 'Only Tile')
            ->assertJsonPath('data.sections.0.content.0.image', '/uploads/ok.jpg');
    }

    public function test_testimonials_section_returns_only_active_testimonials(): void
    {
        HomepageSection::factory()->ofType('TESTIMONIALS')->create(['sort_order' => 1]);
        Testimonial::factory()->create(['author_name' => 'Happy Customer', 'is_active' => true]);
        Testimonial::factory()->create(['author_name' => 'Hidden', 'is_active' => false]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonCount(1, 'data.sections.0.content')
            ->assertJsonPath('data.sections.0.content.0.author_name', 'Happy Customer');
    }

    public function test_hero_section_with_its_own_slides_ignores_global_banners(): void
    {
        Banner::factory()->hero()->create(['title' => 'Global Banner', 'is_active' => true]);

        HomepageSection::factory()->ofType('HERO')->create([
            'sort_order' => 1,
            'config' => [
                'items' => [
                    [
                        'image' => '/uploads/page-hero.jpg',
                        'title' => 'Page Hero',
                        'subtitle' => 'Only on this page',
                        'button_text' => 'Shop Now',
                        'url' => '/shop',
                    ],
                ],
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonCount(1, 'data.sections.0.content')
            ->assertJsonPath('data.sections.0.content.0.title', 'Page Hero')
            ->assertJsonPath('data.sections.0.content.0.image', '/uploads/page-hero.jpg')
            ->assertJsonPath('data.sections.0.content.0.button_text', 'Shop Now');
    }

    public function test_testimonials_section_with_its_own_items_ignores_global_testimonials(): void
    {
        HomepageSection::factory()->ofType('TESTIMONIALS')->create([
            'sort_order' => 1,
            'config' => [
                'items' => [
                    ['author_name' => 'Page Reviewer', 'content' => 'Loved it', 'rating' => 4, 'image' => '/uploads/images/customer.jpg', 'url' => '/shop/silk-saree'],
                ],
            ],
        ]);
        Testimonial::factory()->create(['author_name' => 'Global Reviewer', 'is_active' => true]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonCount(1, 'data.sections.0.content')
            ->assertJsonPath('data.sections.0.content.0.author_name', 'Page Reviewer')
            ->assertJsonPath('data.sections.0.content.0.rating', 4)
            ->assertJsonPath('data.sections.0.content.0.image', '/uploads/images/customer.jpg')
            ->assertJsonPath('data.sections.0.content.0.url', '/shop/silk-saree');
    }

    public function test_product_sections_return_only_live_products(): void
    {
        HomepageSection::factory()->ofType('BEST_SELLERS')->create(['sort_order' => 1, 'config' => ['limit' => 5]]);
        Product::factory()->create(['name' => 'Visible', 'best_seller' => true]);
        Product::factory()->create([
            'name' => 'Draft',
            'best_seller' => true,
            'status' => 'DRAFT',
            'approval_status' => 'PENDING_APPROVAL',
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonCount(1, 'data.sections.0.content')
            ->assertJsonPath('data.sections.0.content.0.name', 'Visible');
    }

    public function test_generic_homepage_sections_return_configured_content(): void
    {
        HomepageSection::factory()->ofType('VALUE_PROPS')->create([
            'sort_order' => 1,
            'config' => [
                'items' => [
                    ['icon' => 'gem', 'title' => 'Curated selection', 'description' => 'Handpicked pieces.'],
                    ['icon' => 'truck', 'title' => 'Fast shipping', 'description' => 'Free over threshold.'],
                ],
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()->assertJsonCount(1, 'data.sections');

        $section = $response->json('data.sections.0');
        $this->assertSame('VALUE_PROPS', $section['type']);
        $this->assertCount(2, $section['content']['items']);
        $this->assertSame('gem', $section['content']['items'][0]['icon']);
    }

    public function test_super_admin_can_reorder_sections(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $hero = HomepageSection::factory()->ofType('HERO')->create(['sort_order' => 0]);
        $categories = HomepageSection::factory()->ofType('CATEGORIES')->create(['sort_order' => 1]);

        $this->putJson('/api/v1/admin/homepage-sections/reorder', [
            'sections' => [
                ['id' => $categories->id],
                ['id' => $hero->id],
            ],
        ])->assertOk();

        $this->assertSame(0, $categories->fresh()->sort_order);
        $this->assertSame(1, $hero->fresh()->sort_order);
    }

    public function test_reorder_does_not_change_section_active_state(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $active = HomepageSection::factory()->ofType('HERO')->create(['sort_order' => 0, 'is_active' => true]);
        $inactive = HomepageSection::factory()->ofType('CATEGORIES')->create(['sort_order' => 1, 'is_active' => false]);

        $this->putJson('/api/v1/admin/homepage-sections/reorder', [
            'sections' => [
                ['id' => $inactive->id],
                ['id' => $active->id],
            ],
        ])->assertOk();

        $this->assertFalse($inactive->fresh()->is_active);
        $this->assertTrue($active->fresh()->is_active);
    }

    public function test_admin_can_save_editors_picks_items_with_empty_optional_fields(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $section = HomepageSection::factory()->ofType('EDITORS_PICKS')->create();

        $this->putJson('/api/v1/admin/homepage-sections/'.$section->id, [
            'type' => 'EDITORS_PICKS',
            'title' => 'Editor’s Picks',
            'subtitle' => null,
            'config' => [
                'items' => [
                    ['image' => '/uploads/one.jpg', 'title' => null, 'subtitle' => null, 'url' => '/collections/blouse'],
                    ['image' => null, 'title' => 'Saree Edit', 'subtitle' => '', 'url' => null],
                ],
            ],
        ])->assertOk();

        $items = $section->fresh()->config['items'];

        $this->assertCount(2, $items);
        $this->assertSame('/uploads/one.jpg', $items[0]['image']);
        $this->assertSame('Saree Edit', $items[1]['title']);
    }

    public function test_split_collage_section_returns_configured_media_and_text(): void
    {
        HomepageSection::factory()->ofType('SPLIT_COLLAGE')->create([
            'title' => 'The Saree Edit',
            'sort_order' => 1,
            'config' => [
                'video' => '/uploads/pre-drape.mp4',
                'poster' => '/uploads/poster.jpg',
                'images' => ['/uploads/wide.jpg', '/uploads/small-a.jpg', '/uploads/small-b.jpg'],
                'eyebrow' => 'THE PERFECT DRAPE, MADE EASY',
                'heading' => 'INSTANT SAREE',
                'description' => 'The sarees you love, expertly pre-draped for you.',
                'button_text' => 'PRE-DRAPE NOW',
                'button_url' => '/collections/pre-draped-sarees',
                'media_position' => 'right',
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'SPLIT_COLLAGE')
            ->assertJsonPath('data.sections.0.content.video', '/uploads/pre-drape.mp4')
            ->assertJsonPath('data.sections.0.content.poster', '/uploads/poster.jpg')
            ->assertJsonCount(3, 'data.sections.0.content.images')
            ->assertJsonPath('data.sections.0.content.images.0', '/uploads/wide.jpg')
            ->assertJsonPath('data.sections.0.content.heading', 'INSTANT SAREE')
            ->assertJsonPath('data.sections.0.content.button_text', 'PRE-DRAPE NOW')
            ->assertJsonPath('data.sections.0.content.button_url', '/collections/pre-draped-sarees')
            ->assertJsonPath('data.sections.0.content.media_position', 'right');
    }

    public function test_admin_can_save_split_collage_with_empty_optional_fields(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $section = HomepageSection::factory()->ofType('SPLIT_COLLAGE')->create();

        $this->putJson('/api/v1/admin/homepage-sections/'.$section->id, [
            'type' => 'SPLIT_COLLAGE',
            'title' => 'The Saree Edit',
            'subtitle' => null,
            'config' => [
                'video' => null,
                'poster' => null,
                'images' => [],
                'eyebrow' => null,
                'heading' => null,
                'description' => null,
                'button_text' => null,
                'button_url' => null,
                'media_position' => 'left',
            ],
        ])->assertOk();

        $config = $section->fresh()->config;

        $this->assertSame([], $config['images']);
        $this->assertSame('left', $config['media_position']);
    }

    public function test_image_banner_section_returns_configured_banner_and_link(): void
    {
        HomepageSection::factory()->ofType('IMAGE_BANNER')->create([
            'title' => 'Bride And Groom Collection',
            'sort_order' => 1,
            'config' => [
                'image' => '/uploads/bride-groom.jpg',
                'image_mobile' => '/uploads/bride-groom-mobile.jpg',
                'alt' => 'Bride And Groom Collection',
                'link_url' => '/collections/bridal',
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'IMAGE_BANNER')
            ->assertJsonPath('data.sections.0.title', 'Bride And Groom Collection')
            ->assertJsonPath('data.sections.0.content.image', '/uploads/bride-groom.jpg')
            ->assertJsonPath('data.sections.0.content.image_mobile', '/uploads/bride-groom-mobile.jpg')
            ->assertJsonPath('data.sections.0.content.alt', 'Bride And Groom Collection')
            ->assertJsonPath('data.sections.0.content.link_url', '/collections/bridal');
    }

    public function test_admin_can_save_image_banner_with_minimal_fields(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $section = HomepageSection::factory()->ofType('IMAGE_BANNER')->create();

        $this->putJson('/api/v1/admin/homepage-sections/'.$section->id, [
            'type' => 'IMAGE_BANNER',
            'title' => 'Bride And Groom Collection',
            'subtitle' => null,
            'config' => [
                'image' => '/uploads/bride-groom.jpg',
                'image_mobile' => null,
                'alt' => null,
                'link_url' => null,
            ],
        ])->assertOk();

        $config = $section->fresh()->config;

        $this->assertSame('/uploads/bride-groom.jpg', $config['image']);
    }

    public function test_video_banner_section_returns_configured_video_and_overlay(): void
    {
        HomepageSection::factory()->ofType('VIDEO_BANNER')->create([
            'title' => 'Because Every Wedding Chapter Deserves A Look Of Its Own',
            'sort_order' => 1,
            'config' => [
                'video' => '/uploads/videos/wedding.mp4',
                'video_mobile' => '/uploads/videos/wedding-mobile.mp4',
                'poster' => '/uploads/images/wedding-poster.jpg',
                'description' => 'Exquisite craftsmanship of premium fabrics, made for moments that matter.',
                'button_text' => 'BOOK YOUR APPOINTMENT',
                'button_url' => '/contact',
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'VIDEO_BANNER')
            ->assertJsonPath('data.sections.0.title', 'Because Every Wedding Chapter Deserves A Look Of Its Own')
            ->assertJsonPath('data.sections.0.content.video', '/uploads/videos/wedding.mp4')
            ->assertJsonPath('data.sections.0.content.video_mobile', '/uploads/videos/wedding-mobile.mp4')
            ->assertJsonPath('data.sections.0.content.poster', '/uploads/images/wedding-poster.jpg')
            ->assertJsonPath('data.sections.0.content.description', 'Exquisite craftsmanship of premium fabrics, made for moments that matter.')
            ->assertJsonPath('data.sections.0.content.button_text', 'BOOK YOUR APPOINTMENT')
            ->assertJsonPath('data.sections.0.content.button_url', '/contact');
    }

    public function test_admin_can_save_video_banner_with_every_field_intact(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $section = HomepageSection::factory()->ofType('VIDEO_BANNER')->create();

        $this->putJson('/api/v1/admin/homepage-sections/'.$section->id, [
            'type' => 'VIDEO_BANNER',
            'title' => 'Handcrafted With Love',
            'subtitle' => null,
            'config' => [
                'video' => '/uploads/videos/bridal.mp4',
                'video_mobile' => '/uploads/videos/bridal-mobile.mp4',
                'poster' => '/uploads/images/bridal-poster.jpg',
                'description' => 'Premium fabrics, made for moments that matter.',
                'button_text' => 'BOOK YOUR APPOINTMENT',
                'button_url' => '/pages/live-video-shopping',
            ],
        ])->assertOk();

        $config = $section->fresh()->config;

        // every configured field must survive the validation whitelist round-trip.
        $this->assertSame('/uploads/videos/bridal.mp4', $config['video']);
        $this->assertSame('/uploads/videos/bridal-mobile.mp4', $config['video_mobile']);
        $this->assertSame('/uploads/images/bridal-poster.jpg', $config['poster']);
        $this->assertSame('Premium fabrics, made for moments that matter.', $config['description']);
        $this->assertSame('BOOK YOUR APPOINTMENT', $config['button_text']);
        $this->assertSame('/pages/live-video-shopping', $config['button_url']);
    }

    public function test_product_carousel_section_returns_configured_cards(): void
    {
        HomepageSection::factory()->ofType('PRODUCT_CAROUSEL')->create([
            'title' => 'HANDCRAFTED WITH LOVE',
            'subtitle' => 'Exquisite craftsmanship of premium fabrics, made for moments that matter.',
            'sort_order' => 1,
            'config' => [
                'button_text' => 'Shop Now',
                'button_url' => '/collections/bridal',
                'items' => [
                    [
                        'image' => '/uploads/images/card-one.jpg',
                        'title' => 'Off White Bridal Lehenga',
                        'price' => '₹1,29,999',
                        'url' => '/products/off-white-bridal-lehenga',
                    ],
                    [
                        'image' => '/uploads/images/card-two.jpg',
                        'title' => 'Ivory Indo Western Sherwani',
                        'price' => '₹89,999',
                        'url' => '/products/ivory-indo-western-sherwani',
                    ],
                ],
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'PRODUCT_CAROUSEL')
            ->assertJsonPath('data.sections.0.title', 'HANDCRAFTED WITH LOVE')
            ->assertJsonPath('data.sections.0.subtitle', 'Exquisite craftsmanship of premium fabrics, made for moments that matter.')
            ->assertJsonPath('data.sections.0.content.button_text', 'Shop Now')
            ->assertJsonPath('data.sections.0.content.button_url', '/collections/bridal')
            ->assertJsonCount(2, 'data.sections.0.content.items')
            ->assertJsonPath('data.sections.0.content.items.0.image', '/uploads/images/card-one.jpg')
            ->assertJsonPath('data.sections.0.content.items.0.title', 'Off White Bridal Lehenga')
            ->assertJsonPath('data.sections.0.content.items.0.price', '₹1,29,999')
            ->assertJsonPath('data.sections.0.content.items.0.url', '/products/off-white-bridal-lehenga')
            ->assertJsonPath('data.sections.0.content.items.1.title', 'Ivory Indo Western Sherwani');
    }

    public function test_admin_can_save_product_carousel_with_every_card_field_intact(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $section = HomepageSection::factory()->ofType('PRODUCT_CAROUSEL')->create();

        $this->putJson('/api/v1/admin/homepage-sections/'.$section->id, [
            'type' => 'PRODUCT_CAROUSEL',
            'title' => 'HANDCRAFTED WITH LOVE',
            'subtitle' => 'Exquisite craftsmanship of premium fabrics, made for moments that matter.',
            'config' => [
                'button_text' => 'Shop Now',
                'button_url' => '/collections/bridal',
                'items' => [
                    [
                        'image' => '/uploads/images/bridal-lehenga.jpg',
                        'title' => 'Off White Bridal Lehenga',
                        'price' => '₹1,29,999',
                        'url' => '/products/off-white-bridal-lehenga',
                    ],
                ],
            ],
        ])->assertOk();

        $section->refresh();
        $config = $section->config;

        // every card field must survive the validation whitelist round-trip.
        $this->assertSame('HANDCRAFTED WITH LOVE', $section->title);
        $this->assertSame('Exquisite craftsmanship of premium fabrics, made for moments that matter.', $section->subtitle);
        $this->assertSame('Shop Now', $config['button_text']);
        $this->assertSame('/collections/bridal', $config['button_url']);
        $this->assertSame('/uploads/images/bridal-lehenga.jpg', $config['items'][0]['image']);
        $this->assertSame('Off White Bridal Lehenga', $config['items'][0]['title']);
        $this->assertSame('₹1,29,999', $config['items'][0]['price']);
        $this->assertSame('/products/off-white-bridal-lehenga', $config['items'][0]['url']);
    }

    public function test_icon_info_section_returns_configured_tiles(): void
    {
        HomepageSection::factory()->ofType('ICON_INFO')->create([
            'sort_order' => 1,
            'config' => [
                'items' => [
                    ['icon' => 'video', 'title' => 'Book a video styling session', 'description' => 'Expert advice, just a video call away.', 'url' => '/contact'],
                    ['icon' => 'sparkles', 'title' => 'Sizing & styling help', 'description' => 'Size guides, fabric notes and order care.', 'url' => '/help'],
                ],
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'ICON_INFO')
            ->assertJsonCount(2, 'data.sections.0.content.items')
            ->assertJsonPath('data.sections.0.content.items.0.icon', 'video')
            ->assertJsonPath('data.sections.0.content.items.0.title', 'Book a video styling session')
            ->assertJsonPath('data.sections.0.content.items.0.url', '/contact')
            ->assertJsonPath('data.sections.0.content.items.1.icon', 'sparkles')
            ->assertJsonPath('data.sections.0.content.items.1.url', '/help');
    }

    public function test_visit_stores_section_returns_configured_copy(): void
    {
        HomepageSection::factory()->ofType('VISIT_STORES')->create([
            'sort_order' => 1,
            'config' => [
                'eyebrow' => 'Visit our stores',
                'title' => 'Visit us or reach out',
                'description' => 'Questions about orders, sizing or styling? Our team is a message away.',
                'button_text' => 'Contact us',
                'button_url' => '/contact',
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'VISIT_STORES')
            ->assertJsonPath('data.sections.0.content.eyebrow', 'Visit our stores')
            ->assertJsonPath('data.sections.0.content.title', 'Visit us or reach out')
            ->assertJsonPath('data.sections.0.content.description', 'Questions about orders, sizing or styling? Our team is a message away.')
            ->assertJsonPath('data.sections.0.content.button_text', 'Contact us')
            ->assertJsonPath('data.sections.0.content.button_url', '/contact');
    }

    public function test_store_locations_section_returns_configured_cards(): void
    {
        HomepageSection::factory()->ofType('STORE_LOCATIONS')->create([
            'title' => 'Visit our Stores',
            'subtitle' => null,
            'sort_order' => 1,
            'config' => [
                'heading' => 'Visit our Stores',
                'items' => [
                    [
                        'image' => '/uploads/mumbai.jpg',
                        'name' => 'Waplia Mumbai',
                        'button_text' => 'Visit Store',
                        'button_url' => '/vendors/1',
                        'secondary_button_text' => 'View Collection',
                        'secondary_button_url' => '/shop',
                    ],
                    [
                        'image' => '/uploads/delhi.jpg',
                        'name' => 'Waplia Delhi',
                        'button_text' => 'Visit Store',
                        'button_url' => '/vendors/2',
                    ],
                ],
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'STORE_LOCATIONS')
            ->assertJsonPath('data.sections.0.content.heading', 'Visit our Stores')
            ->assertJsonCount(2, 'data.sections.0.content.items')
            ->assertJsonPath('data.sections.0.content.items.0.image', '/uploads/mumbai.jpg')
            ->assertJsonPath('data.sections.0.content.items.0.name', 'Waplia Mumbai')
            ->assertJsonPath('data.sections.0.content.items.0.button_text', 'Visit Store')
            ->assertJsonPath('data.sections.0.content.items.0.button_url', '/vendors/1')
            ->assertJsonPath('data.sections.0.content.items.0.secondary_button_text', 'View Collection')
            ->assertJsonPath('data.sections.0.content.items.0.secondary_button_url', '/shop')
            ->assertJsonPath('data.sections.0.content.items.1.name', 'Waplia Delhi')
            ->assertJsonPath('data.sections.0.content.items.1.secondary_button_text', '')
            ->assertJsonPath('data.sections.0.content.items.1.secondary_button_url', '');
    }

    public function test_admin_can_save_store_location_cards(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $section = HomepageSection::factory()->ofType('STORE_LOCATIONS')->create();

        $this->putJson('/api/v1/admin/homepage-sections/'.$section->id, [
            'type' => 'STORE_LOCATIONS',
            'title' => 'Visit our Stores',
            'subtitle' => null,
            'config' => [
                'heading' => 'Visit our Stores',
                'items' => [
                    [
                        'image' => '/uploads/mumbai.jpg',
                        'name' => 'Waplia Mumbai',
                        'button_text' => 'Visit Store',
                        'button_url' => '/vendors/1',
                        'secondary_button_text' => 'View Collection',
                        'secondary_button_url' => '/shop',
                    ],
                ],
            ],
        ])->assertOk();

        $section->refresh();

        $this->assertSame('Visit our Stores', $section->title);
        $this->assertSame('Visit our Stores', $section->config['heading']);
        $this->assertCount(1, $section->config['items']);
        $this->assertSame('Waplia Mumbai', $section->config['items'][0]['name']);
        $this->assertSame('/uploads/mumbai.jpg', $section->config['items'][0]['image']);
        $this->assertSame('/vendors/1', $section->config['items'][0]['button_url']);
        $this->assertSame('View Collection', $section->config['items'][0]['secondary_button_text']);
        $this->assertSame('/shop', $section->config['items'][0]['secondary_button_url']);
    }

    public function test_personalization_strip_returns_configured_copy_and_link(): void
    {
        HomepageSection::factory()->ofType('PERSONALIZATION')->create([
            'title' => 'Curated pieces, styled to your taste',
            'subtitle' => null,
            'sort_order' => 1,
            'config' => [
                'button_text' => 'View Your Edit',
                'button_url' => '/featured-products',
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'PERSONALIZATION')
            ->assertJsonPath('data.sections.0.title', 'Curated pieces, styled to your taste')
            ->assertJsonPath('data.sections.0.content.button_text', 'View Your Edit')
            ->assertJsonPath('data.sections.0.content.button_url', '/featured-products');
    }

    public function test_admin_can_save_personalization_strip_copy_and_link(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $section = HomepageSection::factory()->ofType('PERSONALIZATION')->create();

        $this->putJson('/api/v1/admin/homepage-sections/'.$section->id, [
            'type' => 'PERSONALIZATION',
            'title' => 'Curated pieces, styled to your taste',
            'subtitle' => 'Handpicked for you',
            'config' => [
                'button_text' => 'View Your Edit',
                'button_url' => '/featured-products',
            ],
        ])->assertOk();

        $section->refresh();

        $this->assertSame('Curated pieces, styled to your taste', $section->title);
        $this->assertSame('Handpicked for you', $section->subtitle);
        $this->assertSame('View Your Edit', $section->config['button_text']);
        $this->assertSame('/featured-products', $section->config['button_url']);
    }

    public function test_admin_can_save_personalization_strip_without_a_link(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $section = HomepageSection::factory()->ofType('PERSONALIZATION')->create();

        $this->putJson('/api/v1/admin/homepage-sections/'.$section->id, [
            'type' => 'PERSONALIZATION',
            'title' => 'Curated pieces, styled to your taste',
            'subtitle' => null,
            'config' => [
                'button_text' => null,
                'button_url' => null,
            ],
        ])->assertOk();

        $section->refresh();

        $this->assertNull($section->config['button_text']);
        $this->assertNull($section->config['button_url']);
    }

    public function test_text_banner_section_returns_configured_text_and_button(): void
    {
        HomepageSection::factory()->ofType('TEXT_BANNER')->create([
            'title' => 'Shop With A Stylist',
            'subtitle' => 'Expert advice, just a video call away.',
            'sort_order' => 1,
            'config' => [
                'image' => '/uploads/stylist.jpg',
                'alt' => 'Shop With A Stylist',
                'description' => 'Our Stylists speak English, Hindi, Gujarati & Marathi',
                'button_text' => 'START CALL NOW',
                'button_url' => '/contact',
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'TEXT_BANNER')
            ->assertJsonPath('data.sections.0.title', 'Shop With A Stylist')
            ->assertJsonPath('data.sections.0.subtitle', 'Expert advice, just a video call away.')
            ->assertJsonPath('data.sections.0.content.image', '/uploads/stylist.jpg')
            ->assertJsonPath('data.sections.0.content.alt', 'Shop With A Stylist')
            ->assertJsonPath('data.sections.0.content.description', 'Our Stylists speak English, Hindi, Gujarati & Marathi')
            ->assertJsonPath('data.sections.0.content.button_text', 'START CALL NOW')
            ->assertJsonPath('data.sections.0.content.button_url', '/contact');
    }

    public function test_admin_can_save_text_banner_without_a_background_image(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $section = HomepageSection::factory()->ofType('TEXT_BANNER')->create();

        $this->putJson('/api/v1/admin/homepage-sections/'.$section->id, [
            'type' => 'TEXT_BANNER',
            'title' => 'Shop With A Stylist',
            'subtitle' => 'Expert advice, just a video call away.',
            'config' => [
                'image' => null,
                'alt' => null,
                'description' => 'Our Stylists speak English, Hindi, Gujarati & Marathi',
                'button_text' => 'START CALL NOW',
                'button_url' => '/contact',
            ],
        ])->assertOk();

        $section->refresh();

        $this->assertSame('Shop With A Stylist', $section->title);
        $this->assertSame('Expert advice, just a video call away.', $section->subtitle);
        $this->assertSame('START CALL NOW', $section->config['button_text']);
    }

    public function test_instagram_diaries_section_returns_configured_items(): void
    {
        HomepageSection::factory()->ofType('INSTAGRAM_DIARIES')->create([
            'title' => 'Visit Our Instagram Diaries',
            'sort_order' => 1,
            'config' => [
                'heading' => 'VISIT OUR INSTAGRAM DIARIES',
                'handle' => 'Follow To Know More @waplia',
                'avatar' => '/uploads/avatar.jpg',
                'button_text' => 'Visit Instagram',
                'button_url' => 'https://instagram.com/waplia',
                'items' => [
                    [
                        'image' => '/uploads/diary-1.jpg',
                        'caption' => 'Festive feels',
                        'permalink' => 'https://instagram.com/p/one',
                        'likes' => '1,204',
                        'comments' => '18',
                    ],
                    [
                        'video' => '/uploads/diary-2.mp4',
                        'poster' => '/uploads/diary-2.jpg',
                        'caption' => 'Behind the seams',
                        'permalink' => 'https://instagram.com/p/two',
                    ],
                    [
                        // A tile without any media is dropped.
                        'caption' => 'No media here',
                    ],
                ],
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'INSTAGRAM_DIARIES')
            ->assertJsonPath('data.sections.0.content.heading', 'VISIT OUR INSTAGRAM DIARIES')
            ->assertJsonPath('data.sections.0.content.handle', 'Follow To Know More @waplia')
            ->assertJsonPath('data.sections.0.content.avatar', '/uploads/avatar.jpg')
            ->assertJsonCount(2, 'data.sections.0.content.items');

        $items = $response->json('data.sections.0.content.items');

        $this->assertSame('/uploads/diary-1.jpg', $items[0]['image']);
        $this->assertSame('Behind the seams', $items[1]['caption']);
        $this->assertSame('/uploads/diary-2.mp4', $items[1]['video']);
        $this->assertSame('/uploads/diary-2.jpg', $items[1]['poster']);
        $this->assertSame('https://instagram.com/p/two', $items[1]['permalink']);
    }

    public function test_admin_can_save_instagram_diaries_with_empty_optional_fields(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $section = HomepageSection::factory()->ofType('INSTAGRAM_DIARIES')->create();

        $this->putJson('/api/v1/admin/homepage-sections/'.$section->id, [
            'type' => 'INSTAGRAM_DIARIES',
            'title' => 'Visit Our Instagram Diaries',
            'subtitle' => null,
            'config' => [
                'heading' => 'VISIT OUR INSTAGRAM DIARIES',
                'handle' => null,
                'avatar' => null,
                'button_text' => null,
                'button_url' => null,
                'items' => [
                    [
                        'image' => '/uploads/diary.jpg',
                        'video' => null,
                        'poster' => null,
                        'caption' => null,
                        'permalink' => null,
                        'likes' => null,
                        'comments' => null,
                    ],
                ],
            ],
        ])->assertOk();

        $config = $section->fresh()->config;

        $this->assertSame('VISIT OUR INSTAGRAM DIARIES', $config['heading']);
        $this->assertSame('/uploads/diary.jpg', $config['items'][0]['image']);
    }

    public function test_trending_styles_section_returns_configured_items(): void
    {
        HomepageSection::factory()->ofType('TRENDING_STYLES')->create([
            'title' => 'Trending Styles',
            'sort_order' => 1,
            'config' => [
                'heading' => 'Trending Styles',
                'items' => [
                    [
                        'image' => '/uploads/style-1.jpg',
                        'title' => 'Silk Anarkali Set',
                        'url' => '/product/silk-anarkali-set',
                    ],
                    [
                        'video' => '/uploads/style-2.mp4',
                        'image' => '/uploads/style-2.jpg',
                        'title' => 'Zardosi Lehenga',
                        'url' => '/product/zardosi-lehenga',
                    ],
                    [
                        // A card without any media is dropped.
                        'title' => 'No media here',
                    ],
                ],
            ],
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'TRENDING_STYLES')
            ->assertJsonPath('data.sections.0.content.heading', 'Trending Styles')
            ->assertJsonCount(2, 'data.sections.0.content.items');

        $items = $response->json('data.sections.0.content.items');

        $this->assertSame('/uploads/style-1.jpg', $items[0]['image']);
        $this->assertSame('Silk Anarkali Set', $items[0]['title']);
        $this->assertSame('/product/silk-anarkali-set', $items[0]['url']);
        $this->assertSame('/uploads/style-2.mp4', $items[1]['video']);
    }

    public function test_admin_can_save_trending_styles_with_empty_optional_fields(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $section = HomepageSection::factory()->ofType('TRENDING_STYLES')->create();

        $this->putJson('/api/v1/admin/homepage-sections/'.$section->id, [
            'type' => 'TRENDING_STYLES',
            'title' => 'Trending Styles',
            'subtitle' => null,
            'config' => [
                'heading' => 'Trending Styles',
                'items' => [
                    [
                        'image' => '/uploads/style.jpg',
                        'video' => null,
                        'title' => 'Silk Anarkali Set',
                        'url' => null,
                    ],
                ],
            ],
        ])->assertOk();

        $config = $section->fresh()->config;

        $this->assertSame('Trending Styles', $config['heading']);
        $this->assertSame('/uploads/style.jpg', $config['items'][0]['image']);
    }
}
