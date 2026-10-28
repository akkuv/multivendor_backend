<?php

namespace Tests\Feature;

use App\Enums\HomepageSectionType;
use App\Models\Category;
use App\Models\CategoryPage;
use App\Models\CategoryPageSection;
use App\Models\HomepageSection;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_returns_category_driven_sections_by_slug(): void
    {
        $category = Category::factory()->create(['name' => 'Electronics', 'is_active' => true]);

        $response = $this->getJson('/api/v1/category-pages/'.$category->slug);

        $response->assertOk()
            ->assertJsonPath('data.page.title', 'Electronics')
            ->assertJsonPath('data.page.category.id', $category->id)
            ->assertJsonPath('data.page.is_custom', false)
            ->assertJsonCount(4, 'data.sections');
    }

    public function test_public_page_uses_custom_page_and_falls_back_to_default_sections(): void
    {
        $category = Category::factory()->create(['name' => 'Fashion', 'is_active' => true]);
        CategoryPage::create([
            'category_id' => $category->id,
            'slug' => $category->slug,
            'title' => 'Fashion Hub',
            'is_active' => true,
        ]);
        HomepageSection::factory()->ofType('BEST_SELLERS')->create(); // unrelated, ignore

        $response = $this->getJson('/api/v1/category-pages/'.$category->slug);

        $response->assertOk()
            ->assertJsonPath('data.page.title', 'Fashion Hub')
            ->assertJsonPath('data.page.is_custom', true)
            ->assertJsonCount(4, 'data.sections');
    }

    public function test_public_page_includes_descendant_products(): void
    {
        $parent = Category::factory()->create(['name' => 'Home & Kitchen', 'is_active' => true]);
        $child = Category::factory()->create(['name' => 'Cookware', 'parent_id' => $parent->id, 'is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $parent->id,
            'subcategory_id' => $child->id,
            'featured' => true,
            'best_seller' => true,
        ]);

        $response = $this->getJson('/api/v1/category-pages/'.$parent->slug);

        $response->assertOk()
            ->assertJsonCount(1, 'data.sections.2.content')
            ->assertJsonPath('data.sections.2.content.0.id', $product->id);
    }

    public function test_public_page_renders_every_homepage_section_type(): void
    {
        $category = Category::factory()->create(['name' => 'Crafts', 'is_active' => true]);
        $page = CategoryPage::create([
            'category_id' => $category->id,
            'slug' => $category->slug,
            'title' => 'Crafts',
            'is_active' => true,
        ]);

        CategoryPageSection::create([
            'category_page_id' => $page->id,
            'type' => HomepageSectionType::VideoBanner->value,
            'title' => 'Because Every Chapter Deserves A Look',
            'config' => [
                'video' => '/uploads/videos/clip.webm',
                'description' => 'Exquisite craftsmanship.',
                'button_text' => 'BOOK NOW',
                'button_url' => '/contact',
                'items' => [['image' => '/uploads/images/a.jpg']],
            ],
            'sort_order' => 0,
            'is_active' => true,
        ]);
        CategoryPageSection::create([
            'category_page_id' => $page->id,
            'type' => HomepageSectionType::ProductCarousel->value,
            'title' => 'HANDCRAFTED WITH LOVE',
            'subtitle' => 'Made for moments that matter.',
            'config' => [
                'button_text' => 'Shop Now',
                'button_url' => '/shop',
                'items' => [
                    ['image' => '/uploads/images/one.jpg', 'title' => 'Silk Kurta', 'price' => '11866', 'url' => '/product/silk-kurta'],
                ],
            ],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/category-pages/'.$category->slug);

        $response->assertOk()
            ->assertJsonCount(2, 'data.sections')
            ->assertJsonPath('data.sections.0.type', 'VIDEO_BANNER')
            ->assertJsonPath('data.sections.0.content.video', '/uploads/videos/clip.webm')
            ->assertJsonPath('data.sections.0.content.button_text', 'BOOK NOW')
            ->assertJsonPath('data.sections.1.type', 'PRODUCT_CAROUSEL')
            ->assertJsonPath('data.sections.1.title', 'HANDCRAFTED WITH LOVE')
            ->assertJsonPath('data.sections.1.content.items.0.price', '11866')
            ->assertJsonPath('data.listing.scope_key', 'category_id');
    }

    public function test_page_sections_never_fall_back_to_shared_collections(): void
    {
        $category = Category::factory()->create(['name' => 'Garden', 'is_active' => true]);
        $page = CategoryPage::create([
            'category_id' => $category->id,
            'slug' => $category->slug,
            'title' => 'Garden',
            'is_active' => true,
        ]);
        HomepageSection::factory()->ofType('HERO')->create();
        HomepageSection::factory()->ofType('TESTIMONIALS')->create();
        Product::factory()->create(['category_id' => $category->id, 'best_seller' => true]);

        CategoryPageSection::create([
            'category_page_id' => $page->id,
            'type' => HomepageSectionType::Hero->value,
            'config' => null,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        CategoryPageSection::create([
            'category_page_id' => $page->id,
            'type' => HomepageSectionType::BestSellers->value,
            'config' => null,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/category-pages/'.$category->slug);

        $response->assertOk()
            ->assertJsonCount(2, 'data.sections')
            ->assertJsonCount(0, 'data.sections.0.content');

        // A category page's unconfigured product section is scoped to the
        // category subtree rather than the global homepage collection.
        $response->assertJsonCount(1, 'data.sections.1.content');
    }

    public function test_products_endpoint_filters_by_category_tree(): void
    {
        $parent = Category::factory()->create(['name' => 'Home & Kitchen', 'is_active' => true]);
        $child = Category::factory()->create(['name' => 'Cookware', 'parent_id' => $parent->id, 'is_active' => true]);
        $grandchild = Category::factory()->create(['name' => 'Pans', 'parent_id' => $child->id, 'is_active' => true]);
        $inScope = Product::factory()->create([
            'category_id' => $grandchild->id,
            'subcategory_id' => $grandchild->id,
        ]);
        $outOfScope = Product::factory()->create([
            'category_id' => $parent->id,
            'subcategory_id' => $parent->id,
        ]);

        $this->getJson('/api/v1/products?category_scope_id='.$child->id)
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $inScope->id);

        $this->getJson('/api/v1/products?category_id='.$child->id)
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_inactive_category_returns_404(): void
    {
        $category = Category::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/category-pages/'.$category->slug)
            ->assertNotFound();
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->getJson('/api/v1/category-pages/does-not-exist')
            ->assertNotFound();
    }

    public function test_super_admin_can_create_update_delete_category_page(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');
        $category = Category::factory()->create(['name' => 'Beauty']);

        $create = $this->postJson('/api/v1/admin/category-pages', [
            'category_id' => $category->id,
            'title' => 'Beauty Shop',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.title', 'Beauty Shop')
            ->assertJsonPath('data.slug', $category->slug);

        $pageId = $create->json('data.id');

        $this->putJson("/api/v1/admin/category-pages/{$pageId}", ['title' => 'Beauty Emporium'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Beauty Emporium');

        $this->deleteJson("/api/v1/admin/category-pages/{$pageId}")->assertOk();
    }

    public function test_super_admin_can_manage_sections(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');
        $category = Category::factory()->create(['name' => 'Toys']);
        $page = CategoryPage::create([
            'category_id' => $category->id,
            'slug' => $category->slug,
            'title' => 'Toys',
            'is_active' => true,
        ]);

        $create = $this->postJson("/api/v1/admin/category-pages/{$page->id}/sections", [
            'type' => 'BEST_SELLERS',
            'title' => 'Top Toys',
        ]);

        $create->assertCreated()->assertJsonPath('data.title', 'Top Toys');
        $sectionId = $create->json('data.id');

        $this->putJson("/api/v1/admin/category-page-sections/{$sectionId}", ['title' => 'Trending Toys'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Trending Toys');

        $this->deleteJson("/api/v1/admin/category-page-sections/{$sectionId}")->assertOk();
    }
}
