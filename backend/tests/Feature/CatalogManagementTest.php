<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Size;
use App\Models\SizeGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function adminUser(): User
    {
        $admin = User::factory()->superAdmin()->create();

        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_can_list_and_search_brands(): void
    {
        $this->adminUser();

        Brand::factory()->create(['name' => 'Apple']);
        Brand::factory()->create(['name' => 'Samsung']);
        Brand::factory()->count(3)->create();

        $this->getJson('/api/v1/admin/brands')
            ->assertOk()
            ->assertJsonStructure(['data' => ['data' => []]])
            ->assertJsonFragment(['total' => 5]);

        $this->getJson('/api/v1/admin/brands?search=apple')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Apple']);
    }

    public function test_admin_can_create_brand_with_auto_slug(): void
    {
        $this->adminUser();

        $this->postJson('/api/v1/admin/brands', [
            'name' => 'My Brand',
            'description' => 'A test brand',
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'my-brand')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_brand_slug_must_be_unique(): void
    {
        $this->adminUser();

        Brand::factory()->create(['name' => 'Apple', 'slug' => 'apple']);

        $this->postJson('/api/v1/admin/brands', ['name' => 'Apple'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_admin_can_update_and_toggle_brand(): void
    {
        $this->adminUser();

        $brand = Brand::factory()->create();

        $this->putJson("/api/v1/admin/brands/{$brand->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $this->putJson("/api/v1/admin/brands/{$brand->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_admin_can_soft_delete_brand(): void
    {
        $this->adminUser();

        $brand = Brand::factory()->create();

        $this->deleteJson("/api/v1/admin/brands/{$brand->id}")
            ->assertOk();

        $this->assertSoftDeleted('brands', ['id' => $brand->id]);
    }

    public function test_admin_can_manage_attributes_and_values(): void
    {
        $this->adminUser();

        $attribute = Attribute::factory()->create(['name' => 'RAM']);

        $this->postJson('/api/v1/admin/attribute-values', [
            'attribute_id' => $attribute->id,
            'value' => '8 GB',
        ])
            ->assertCreated()
            ->assertJsonPath('data.value', '8 GB');

        $this->getJson("/api/v1/admin/attributes/{$attribute->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.values');
    }

    public function test_attribute_value_must_be_unique_within_attribute(): void
    {
        $this->adminUser();

        $attribute = Attribute::factory()->create();
        AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'value' => '8 GB']);

        $this->postJson('/api/v1/admin/attribute-values', [
            'attribute_id' => $attribute->id,
            'value' => '8 GB',
        ])->assertStatus(422);
    }

    public function test_attribute_with_values_cannot_be_deleted(): void
    {
        $this->adminUser();

        $attribute = Attribute::factory()->create();
        AttributeValue::factory()->create(['attribute_id' => $attribute->id]);

        $this->deleteJson("/api/v1/admin/attributes/{$attribute->id}")
            ->assertStatus(422);
    }

    public function test_admin_can_filter_attribute_values_by_attribute(): void
    {
        $this->adminUser();

        $color = Attribute::factory()->create(['name' => 'Color']);
        $size = Attribute::factory()->create(['name' => 'Size']);

        AttributeValue::factory()->create(['attribute_id' => $color->id, 'value' => 'Red']);
        AttributeValue::factory()->create(['attribute_id' => $color->id, 'value' => 'Blue']);
        AttributeValue::factory()->create(['attribute_id' => $size->id, 'value' => 'M']);

        $this->getJson('/api/v1/admin/attribute-values?attribute_id='.$color->id)
            ->assertOk()
            ->assertJsonCount(2, 'data.data');
    }

    public function test_admin_can_manage_colors(): void
    {
        $this->adminUser();

        $this->postJson('/api/v1/admin/colors', [
            'name' => 'Teal',
            'hex_code' => '#008080',
        ])
            ->assertCreated()
            ->assertJsonPath('data.hex_code', '#008080');

        $color = Color::factory()->create();

        $this->putJson("/api/v1/admin/colors/{$color->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_color_hex_must_be_valid(): void
    {
        $this->adminUser();

        $this->postJson('/api/v1/admin/colors', [
            'name' => 'Bad',
            'hex_code' => 'yellow',
        ])->assertStatus(422);
    }

    public function test_admin_can_manage_size_groups_and_sizes(): void
    {
        $this->adminUser();

        $clothing = $this->postJson('/api/v1/admin/size-groups', ['name' => 'Clothing'])
            ->assertCreated()
            ->json('data');

        $this->postJson('/api/v1/admin/sizes', [
            'size_group_id' => $clothing['id'],
            'name' => 'M',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'M');

        $this->postJson('/api/v1/admin/sizes', [
            'size_group_id' => $clothing['id'],
            'name' => 'L',
        ])->assertCreated();

        $this->getJson('/api/v1/admin/sizes?size_group_id='.$clothing['id'])
            ->assertOk()
            ->assertJsonCount(2, 'data.data');

        $this->getJson("/api/v1/admin/size-groups/{$clothing['id']}")
            ->assertOk()
            ->assertJsonCount(2, 'data.sizes');
    }

    public function test_size_name_must_be_unique_within_group(): void
    {
        $this->adminUser();

        $group = SizeGroup::factory()->create();
        Size::factory()->create(['size_group_id' => $group->id, 'name' => 'M']);

        $this->postJson('/api/v1/admin/sizes', [
            'size_group_id' => $group->id,
            'name' => 'M',
        ])->assertStatus(422);
    }

    public function test_admin_can_manage_category_hierarchy_and_meta(): void
    {
        $this->adminUser();

        $parent = $this->postJson('/api/v1/admin/categories', [
            'name' => 'Electronics',
            'meta_title' => 'Electronics Store',
        ])->assertCreated()->json('data');

        $child = $this->postJson('/api/v1/admin/categories', [
            'name' => 'Mobile Accessories',
            'parent_id' => $parent['id'],
        ])->assertCreated()->json('data');

        $this->postJson('/api/v1/admin/categories', [
            'name' => 'Chargers',
            'parent_id' => $child['id'],
        ])->assertCreated();

        $this->getJson('/api/v1/admin/categories/tree')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Electronics')
            ->assertJsonPath('data.0.children.0.name', 'Mobile Accessories');
    }

    public function test_category_with_children_cannot_be_deleted(): void
    {
        $this->adminUser();

        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);

        $this->deleteJson("/api/v1/admin/categories/{$parent->id}")
            ->assertStatus(422);
    }

    public function test_only_super_admin_can_access_catalog_management(): void
    {
        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/admin/brands')->assertForbidden();
        $this->getJson('/api/v1/admin/attributes')->assertForbidden();
    }
}
