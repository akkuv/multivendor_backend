<?php

namespace Tests\Feature;

use App\Models\CustomPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomPageTestimonialsSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_testimonials_with_full_fields_on_custom_page(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $page = CustomPage::factory()->create(['slug' => 'women/women-ethnic-wear']);

        $items = [
            [
                'author_name' => 'Priya S.',
                'author_role' => 'Verified Customer',
                'author_avatar' => '/uploads/avatars/1.jpg',
                'image' => '/uploads/images/1.webp',
                'url' => '/product/x',
                'content' => 'Amazing quality and great fit.',
                'rating' => 5,
            ],
        ];

        $response = $this->postJson('/api/v1/admin/homepage-sections', [
            'custom_page_id' => $page->id,
            'type' => 'TESTIMONIALS',
            'title' => 'Customer Stories',
            'config' => ['items' => $items, 'limit' => 10],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.config.items.0.author_name', 'Priya S.')
            ->assertJsonPath('data.config.items.0.content', 'Amazing quality and great fit.')
            ->assertJsonPath('data.config.items.0.rating', 5);

        $this->assertDatabaseHas('homepage_sections', [
            'custom_page_id' => $page->id,
            'type' => 'TESTIMONIALS',
            'title' => 'Customer Stories',
        ]);

        $section = $page->sections()->firstWhere('type', 'TESTIMONIALS');
        $this->assertNotNull($section);
        $this->assertSame('Priya S.', $section->config['items'][0]['author_name']);
        $this->assertSame(5, $section->config['items'][0]['rating']);
    }
}
