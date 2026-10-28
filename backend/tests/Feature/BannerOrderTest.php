<?php

namespace Tests\Feature;

use App\Enums\BannerPosition;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_banner_with_relative_url_can_be_updated(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $banner = Banner::create([
            'title' => 'Welcome',
            'url' => '/shop',
            'position' => BannerPosition::Hero->value,
            'sort_order' => 0,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/admin/banners/{$banner->id}", [
                'title' => 'Welcome updated',
                'url' => '/shop?sort=discount',
                'sort_order' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.sort_order', 3);

        $this->assertDatabaseHas('banners', [
            'id' => $banner->id,
            'url' => '/shop?sort=discount',
            'sort_order' => 3,
        ]);
    }

    public function test_banner_invalid_url_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $banner = Banner::create([
            'title' => 'Welcome',
            'url' => '/shop',
            'position' => BannerPosition::Hero->value,
            'sort_order' => 0,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/admin/banners/{$banner->id}", [
                'url' => 'not a url',
            ])
            ->assertUnprocessable();
    }

    public function test_banners_are_ordered_by_sort_order_then_id(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Banner::create(['title' => 'Second', 'position' => BannerPosition::Hero->value, 'sort_order' => 1]);
        Banner::create(['title' => 'Third', 'position' => BannerPosition::Hero->value, 'sort_order' => 2]);
        Banner::create(['title' => 'First', 'position' => BannerPosition::Hero->value, 'sort_order' => 0]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/banners?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.data.0.title', 'First')
            ->assertJsonPath('data.data.1.title', 'Second')
            ->assertJsonPath('data.data.2.title', 'Third');
    }
}
