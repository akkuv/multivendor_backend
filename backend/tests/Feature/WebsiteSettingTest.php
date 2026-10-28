<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebsiteSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_content_returns_default_navbar_and_footer(): void
    {
        $response = $this->getJson('/api/v1/website-content');

        $response->assertOk()
            ->assertJsonPath('data.navbar.logo.text', 'Waplia')
            ->assertJsonPath('data.navbar.announcement.text', 'Free shipping on orders over ₹499')
            ->assertJsonCount(2, 'data.navbar.links')
            ->assertJsonPath('data.navbar.departments.enabled', true)
            ->assertJsonPath('data.navbar.departments.category_ids', null)
            ->assertJsonPath('data.navbar.departments.show_all', true)
            ->assertJsonPath('data.navbar.departments.show_shop_all', true)
            ->assertJsonPath('data.navbar.departments.show_collections', true)
            ->assertJsonCount(3, 'data.footer.columns')
            ->assertJsonPath('data.footer.social.enabled', true)
            ->assertJsonPath('data.footer.social.style', 'icons')
            ->assertJsonPath('data.footer.social.title', 'Follow us')
            ->assertJsonCount(0, 'data.footer.social.links')
            ->assertJsonPath('data.footer.copyright', 'All rights reserved.');
    }

    public function test_super_admin_can_update_navbar_and_footer(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $response = $this->putJson('/api/v1/admin/website-settings', [
            'navbar' => [
                'announcement' => ['enabled' => true, 'text' => 'Mega sale today', 'url' => '/shop'],
                'logo' => ['text' => 'Waplia Store', 'image' => null, 'url' => '/'],
                'links' => [
                    ['label' => 'Home', 'url' => '/', 'new_tab' => false],
                    ['label' => 'New Arrivals', 'url' => '/shop?sort=latest', 'new_tab' => true],
                ],
            ],
            'footer' => [
                'columns' => [
                    ['title' => 'Company', 'links' => [['label' => 'About', 'url' => '/about', 'new_tab' => false]]],
                ],
                'copyright' => 'Made with care.',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.navbar.announcement.text', 'Mega sale today')
            ->assertJsonPath('data.navbar.announcement.url', '/shop')
            ->assertJsonPath('data.navbar.logo.text', 'Waplia Store')
            ->assertJsonCount(2, 'data.navbar.links')
            ->assertJsonPath('data.navbar.links.1.new_tab', true)
            ->assertJsonCount(1, 'data.footer.columns')
            ->assertJsonPath('data.footer.copyright', 'Made with care.');

        $this->getJson('/api/v1/website-content')
            ->assertOk()
            ->assertJsonPath('data.navbar.announcement.text', 'Mega sale today')
            ->assertJsonPath('data.footer.columns.0.title', 'Company');
    }

    public function test_update_prunes_invalid_navbar_rows(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $response = $this->putJson('/api/v1/admin/website-settings', [
            'navbar' => [
                'links' => [
                    ['label' => 'Keep me', 'url' => '/keep', 'new_tab' => false],
                    ['label' => '', 'url' => '/missing-label'],
                    'not-an-array',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data.navbar.links')
            ->assertJsonPath('data.navbar.links.0.label', 'Keep me');
    }

    public function test_partial_update_preserves_other_feature(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $this->putJson('/api/v1/admin/website-settings', [
            'footer' => ['copyright' => 'Updated footer only.'],
        ])->assertOk();

        $this->getJson('/api/v1/website-content')
            ->assertOk()
            ->assertJsonPath('data.footer.copyright', 'Updated footer only.')
            ->assertJsonPath('data.navbar.logo.text', 'Waplia');
    }

    public function test_invalid_payload_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $this->putJson('/api/v1/admin/website-settings', [
            'navbar' => 'not-an-array',
        ])->assertStatus(422);
    }

    public function test_admin_can_control_departments_categories(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $response = $this->putJson('/api/v1/admin/website-settings', [
            'navbar' => [
                'departments' => [
                    'enabled' => true,
                    'category_ids' => [1, '14', 'not-a-number', 1, 99],
                    'show_all' => false,
                    'show_shop_all' => false,
                    'show_collections' => false,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.navbar.departments.enabled', true)
            ->assertJsonPath('data.navbar.departments.category_ids', [1, 14, 99])
            ->assertJsonPath('data.navbar.departments.show_all', false)
            ->assertJsonPath('data.navbar.departments.show_shop_all', false)
            ->assertJsonPath('data.navbar.departments.show_collections', false);

        $this->getJson('/api/v1/website-content')
            ->assertOk()
            ->assertJsonPath('data.navbar.departments.category_ids', [1, 14, 99]);
    }

    public function test_admin_can_hide_the_departments_bar(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $this->putJson('/api/v1/admin/website-settings', [
            'navbar' => [
                'departments' => ['enabled' => false],
            ],
        ])->assertOk()
            ->assertJsonPath('data.navbar.departments.enabled', false);
    }

    public function test_admin_can_give_each_navigation_link_its_own_departments_bar(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $response = $this->putJson('/api/v1/admin/website-settings', [
            'navbar' => [
                'links' => [
                    ['label' => 'Home', 'url' => '/', 'new_tab' => false],
                    [
                        'label' => 'Sarees',
                        'url' => '/category/saree',
                        'new_tab' => false,
                        'departments' => [
                            'enabled' => true,
                            'category_ids' => [3, '7', 'nope', 3],
                            'show_all' => false,
                            'show_shop_all' => true,
                            'show_collections' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.navbar.links.0.departments', null)
            ->assertJsonPath('data.navbar.links.1.departments.enabled', true)
            ->assertJsonPath('data.navbar.links.1.departments.category_ids', [3, 7])
            ->assertJsonPath('data.navbar.links.1.departments.show_all', false)
            ->assertJsonPath('data.navbar.links.1.departments.show_shop_all', true)
            ->assertJsonPath('data.navbar.links.1.departments.show_collections', false);

        $this->getJson('/api/v1/website-content')
            ->assertOk()
            ->assertJsonPath('data.navbar.links.1.departments.category_ids', [3, 7]);
    }

    public function test_links_sent_without_a_departments_key_keep_the_stored_override(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $this->putJson('/api/v1/admin/website-settings', [
            'navbar' => [
                'links' => [
                    [
                        'label' => 'Women',
                        'url' => '/page/women-ethnic-wear',
                        'new_tab' => false,
                        'departments' => [
                            'enabled' => true,
                            'category_ids' => [10],
                            'show_all' => false,
                            'show_shop_all' => true,
                            'show_collections' => false,
                        ],
                    ],
                ],
            ],
        ])->assertOk();

        // A client that does not know about the feature omits the key entirely.
        $this->putJson('/api/v1/admin/website-settings', [
            'navbar' => [
                'links' => [
                    ['label' => 'Women', 'url' => '/page/women-ethnic-wear', 'new_tab' => false],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.navbar.links.0.departments.category_ids', [10])
            ->assertJsonPath('data.navbar.links.0.departments.show_collections', false);
    }

    public function test_links_sent_with_an_explicit_null_departments_clear_the_override(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $this->putJson('/api/v1/admin/website-settings', [
            'navbar' => [
                'links' => [
                    [
                        'label' => 'Women',
                        'url' => '/page/women-ethnic-wear',
                        'new_tab' => false,
                        'departments' => [
                            'enabled' => true,
                            'category_ids' => [10],
                            'show_all' => false,
                            'show_shop_all' => true,
                            'show_collections' => false,
                        ],
                    ],
                ],
            ],
        ])->assertOk();

        $this->putJson('/api/v1/admin/website-settings', [
            'navbar' => [
                'links' => [
                    [
                        'label' => 'Women',
                        'url' => '/page/women-ethnic-wear',
                        'new_tab' => false,
                        'departments' => null,
                    ],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.navbar.links.0.departments', null);
    }

    public function test_admin_can_manage_footer_social_links(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $response = $this->putJson('/api/v1/admin/website-settings', [
            'footer' => [
                'social' => [
                    'enabled' => true,
                    'title' => 'Connect with us',
                    'description' => 'Community highlights and trend stories.',
                    'style' => 'labeled',
                    'links' => [
                        ['platform' => 'facebook', 'url' => 'https://facebook.com/waplia', 'label' => 'Facebook', 'enabled' => true],
                        ['platform' => 'instagram', 'url' => 'https://instagram.com/waplia', 'label' => '', 'enabled' => true],
                        ['platform' => 'unknown-network', 'url' => 'https://example.com/wall', 'label' => 'Custom', 'enabled' => true],
                        ['platform' => 'tiktok', 'url' => '  ', 'label' => 'Gone', 'enabled' => true],
                        'not-an-array',
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.footer.social.enabled', true)
            ->assertJsonPath('data.footer.social.title', 'Connect with us')
            ->assertJsonPath('data.footer.social.description', 'Community highlights and trend stories.')
            ->assertJsonPath('data.footer.social.style', 'labeled')
            ->assertJsonCount(3, 'data.footer.social.links')
            ->assertJsonPath('data.footer.social.links.0.platform', 'facebook')
            ->assertJsonPath('data.footer.social.links.0.url', 'https://facebook.com/waplia')
            ->assertJsonPath('data.footer.social.links.1.platform', 'instagram')
            ->assertJsonPath('data.footer.social.links.2.platform', 'website')
            ->assertJsonPath('data.footer.social.links.2.label', 'Custom');

        $this->getJson('/api/v1/website-content')
            ->assertOk()
            ->assertJsonPath('data.footer.social.style', 'labeled')
            ->assertJsonCount(3, 'data.footer.social.links');
    }

    public function test_admin_can_disable_and_toggle_footer_social(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin, 'sanctum');

        $this->putJson('/api/v1/admin/website-settings', [
            'footer' => [
                'social' => [
                    'enabled' => false,
                    'style' => 'icons',
                    'links' => [['platform' => 'youtube', 'url' => 'https://youtube.com/@waplia', 'label' => '', 'enabled' => false]],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.footer.social.enabled', false)
            ->assertJsonPath('data.footer.social.links.0.enabled', false)
            ->assertJsonPath('data.footer.social.links.0.platform', 'youtube');
    }

    public function test_legacy_flat_social_shape_is_converted_by_migration(): void
    {
        DB::table('website_settings')->where('id', 1)->update([
            'footer' => json_encode([
                'about' => ['text' => 'Desc'],
                'columns' => [],
                'social' => [
                    'facebook' => 'https://facebook.com/waplia',
                    'instagram' => null,
                    'twitter' => 'https://twitter.com/waplia',
                    'youtube' => null,
                    'linkedin' => null,
                    'whatsapp' => null,
                ],
                'contact' => ['email' => null, 'phone' => null, 'address' => null],
                'copyright' => 'All rights reserved.',
            ]),
        ]);

        $migration = require database_path('migrations/2026_09_30_000000_convert_footer_social_to_links.php');
        $migration->up();

        $this->getJson('/api/v1/website-content')
            ->assertOk()
            ->assertJsonPath('data.footer.social.enabled', true)
            ->assertJsonCount(2, 'data.footer.social.links')
            ->assertJsonPath('data.footer.social.links.0.platform', 'facebook')
            ->assertJsonPath('data.footer.social.links.0.url', 'https://facebook.com/waplia')
            ->assertJsonPath('data.footer.social.links.1.platform', 'twitter');
    }
}
