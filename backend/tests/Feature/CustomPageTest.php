<?php

namespace Tests\Feature;

use App\Models\CustomPage;
use App\Models\HomepageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_page_with_an_auto_generated_slug(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $response = $this->postJson('/api/v1/admin/pages', ['title' => 'Diwali Sale']);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Diwali Sale')
            ->assertJsonPath('data.slug', 'diwali-sale');

        $this->assertDatabaseHas('custom_pages', ['slug' => 'diwali-sale']);
    }

    public function test_page_slug_can_be_an_editable_nested_path(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/v1/admin/pages', [
            'title' => 'Women Ethnic Wear',
            'slug' => 'Women/Women Ethnic Wear',
        ])->assertCreated()->assertJsonPath('data.slug', 'women/women-ethnic-wear');

        $this->assertDatabaseHas('custom_pages', ['slug' => 'women/women-ethnic-wear']);

        // The page is reachable at the full nested path.
        $this->getJson('/api/v1/pages/women/women-ethnic-wear')
            ->assertOk()
            ->assertJsonPath('data.page.slug', 'women/women-ethnic-wear');

        // The slug is editable: renaming moves the page to the new path.
        $page = CustomPage::query()->where('slug', 'women/women-ethnic-wear')->firstOrFail();

        $this->putJson('/api/v1/admin/pages/'.$page->id, ['slug' => 'women/festive'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'women/festive');

        $this->getJson('/api/v1/pages/women/festive')->assertOk();
        $this->getJson('/api/v1/pages/women/women-ethnic-wear')->assertNotFound();
    }

    public function test_page_slug_collisions_get_a_suffix(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/v1/admin/pages', ['title' => 'Festive'])->assertCreated();
        $this->postJson('/api/v1/admin/pages', ['title' => 'Festive'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'festive-2');
    }

    public function test_super_admin_can_update_and_toggle_a_page(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $page = CustomPage::factory()->create(['slug' => 'old-url', 'is_active' => true]);

        $this->putJson('/api/v1/admin/pages/'.$page->id, [
            'title' => 'New Title',
            'slug' => 'new-url',
        ])->assertOk()->assertJsonPath('data.slug', 'new-url');

        $this->putJson('/api/v1/admin/pages/'.$page->id.'/toggle')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_sections_are_scoped_to_their_page(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $page = CustomPage::factory()->create();

        HomepageSection::factory()->ofType('HERO')->create(['sort_order' => 0]);

        $this->postJson('/api/v1/admin/homepage-sections', [
            'custom_page_id' => $page->id,
            'type' => 'BEST_SELLERS',
            'title' => 'Page only',
        ])->assertCreated()->assertJsonPath('data.custom_page_id', $page->id);

        // Homepage (no scope) still returns only the homepage section.
        $this->getJson('/api/v1/admin/homepage-sections')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'HERO');

        // Scoped query returns only the page's own section.
        $this->getJson('/api/v1/admin/homepage-sections?custom_page_id='.$page->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'BEST_SELLERS')
            ->assertJsonPath('data.0.title', 'Page only');
    }

    public function test_public_page_returns_its_own_sections_only(): void
    {
        $page = CustomPage::factory()->create(['slug' => 'diwali-sale', 'is_active' => true]);

        HomepageSection::factory()->ofType('HERO')->create(['sort_order' => 0]);
        HomepageSection::factory()->ofType('BEST_SELLERS')->create([
            'custom_page_id' => $page->id,
            'sort_order' => 0,
        ]);

        $this->getJson('/api/v1/pages/diwali-sale')
            ->assertOk()
            ->assertJsonPath('data.page.slug', 'diwali-sale')
            ->assertJsonCount(1, 'data.sections')
            ->assertJsonPath('data.sections.0.type', 'BEST_SELLERS');

        // The homepage must never leak a custom page's sections.
        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonCount(1, 'data.sections')
            ->assertJsonPath('data.sections.0.type', 'HERO');
    }

    public function test_public_page_returns_not_found_for_missing_or_inactive_pages(): void
    {
        CustomPage::factory()->create(['slug' => 'hidden', 'is_active' => false]);

        $this->getJson('/api/v1/pages/hidden')->assertNotFound();
        $this->getJson('/api/v1/pages/does-not-exist')->assertNotFound();
    }

    public function test_deleting_a_page_removes_its_sections(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $page = CustomPage::factory()->create();
        $section = HomepageSection::factory()->ofType('HERO')->create(['custom_page_id' => $page->id]);

        $this->deleteJson('/api/v1/admin/pages/'.$page->id)->assertOk();

        $this->assertDatabaseMissing('homepage_sections', ['id' => $section->id]);
    }
}
