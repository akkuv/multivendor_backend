<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function adminUser(): User
    {
        $admin = User::factory()->superAdmin()->create();

        Sanctum::actingAs($admin);

        return $admin;
    }

    protected function vendorUser(): array
    {
        $user = User::factory()->vendor()->create();
        $vendor = VendorProfile::factory()->create([
            'user_id' => $user->id,
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user);

        return [$user, $vendor];
    }

    public function test_storefront_lists_only_active_collections_with_counts(): void
    {
        $active = Collection::factory()->create(['name' => 'Jewellery', 'slug' => 'jewellery', 'is_active' => true]);
        Collection::factory()->create(['is_active' => false]);

        $product = Product::factory()->create();
        $active->products()->attach($product->id);

        $response = $this->getJson('/api/v1/collections');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'jewellery')
            ->assertJsonPath('data.0.products_count', 1);
    }

    public function test_storefront_can_show_collection_by_slug(): void
    {
        Collection::factory()->create([
            'name' => 'Jewellery',
            'slug' => 'jewellery',
            'gender' => 'female',
        ]);

        $this->getJson('/api/v1/collections/jewellery')
            ->assertOk()
            ->assertJsonPath('data.slug', 'jewellery')
            ->assertJsonPath('data.gender', 'female');
    }

    public function test_inactive_collection_is_not_shown(): void
    {
        Collection::factory()->create(['slug' => 'hidden', 'is_active' => false]);

        $this->getJson('/api/v1/collections/hidden')->assertNotFound();
    }

    public function test_products_can_be_filtered_by_collection(): void
    {
        $collection = Collection::factory()->create();
        $included = Product::factory()->create(['name' => 'Included Piece']);
        $excluded = Product::factory()->create(['name' => 'Excluded Piece']);
        $collection->products()->attach($included->id);

        $response = $this->getJson("/api/v1/products?collection_id={$collection->id}");

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.name', 'Included Piece');
    }

    public function test_admin_can_create_collection_with_gender_and_products(): void
    {
        $this->adminUser();
        $product = Product::factory()->create();

        $response = $this->postJson('/api/v1/admin/collections', [
            'name' => 'Men Wedding Wear',
            'gender' => 'male',
            'description' => 'Groom looks',
            'product_ids' => [$product->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'men-wedding-wear')
            ->assertJsonPath('data.gender', 'male')
            ->assertJsonPath('data.product_ids', [$product->id]);

        $this->assertDatabaseHas('collections', ['slug' => 'men-wedding-wear', 'gender' => 'male']);
        $this->assertDatabaseHas('collection_product', [
            'collection_id' => $response->json('data.id'),
            'product_id' => $product->id,
        ]);
    }

    public function test_admin_can_update_and_toggle_collection(): void
    {
        $this->adminUser();
        $collection = Collection::factory()->create(['name' => 'Old Name']);

        $this->putJson("/api/v1/admin/collections/{$collection->id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->putJson("/api/v1/admin/collections/{$collection->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_collection_slug_must_be_unique(): void
    {
        $this->adminUser();
        Collection::factory()->create(['slug' => 'jewellery']);

        $this->postJson('/api/v1/admin/collections', ['name' => 'Jewellery'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_admin_can_soft_delete_collection(): void
    {
        $this->adminUser();
        $collection = Collection::factory()->create();

        $this->deleteJson("/api/v1/admin/collections/{$collection->id}")->assertOk();

        $this->assertSoftDeleted('collections', ['id' => $collection->id]);
    }

    public function test_vendor_can_assign_collections_when_creating_product(): void
    {
        [, $vendor] = $this->vendorUser();
        $collection = Collection::factory()->create();
        $category = Category::factory()->create();

        $response = $this->postJson('/api/v1/vendor/products', [
            'category_id' => $category->id,
            'name' => 'Vendor Collection Piece',
            'price' => 1999,
            'stock' => 5,
            'collection_ids' => [$collection->id],
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('collection_product', [
            'collection_id' => $collection->id,
            'product_id' => $response->json('data.id'),
        ]);
    }

    public function test_vendor_can_update_product_collections(): void
    {
        [, $vendor] = $this->vendorUser();
        $collection = Collection::factory()->create();
        $other = Collection::factory()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);
        $product->collections()->attach($collection->id);

        $this->putJson("/api/v1/vendor/products/{$product->id}", [
            'collection_ids' => [$other->id],
        ])->assertOk();

        $this->assertDatabaseMissing('collection_product', [
            'product_id' => $product->id,
            'collection_id' => $collection->id,
        ]);
        $this->assertDatabaseHas('collection_product', [
            'product_id' => $product->id,
            'collection_id' => $other->id,
        ]);
    }

    public function test_only_super_admin_can_manage_collections(): void
    {
        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/admin/collections')->assertForbidden();
    }
}
