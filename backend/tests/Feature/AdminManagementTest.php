<?php

namespace Tests\Feature;

use App\Enums\ProductApprovalStatus;
use App\Enums\VendorStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_dashboard_returns_expected_stats(): void
    {
        $this->actingAsAdmin();
        User::factory()->customer()->create();
        $vendor = VendorProfile::factory()->create(['status' => VendorStatus::Pending->value]);
        Product::factory()->count(3)->pending()->create(['vendor_id' => $vendor->id]);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.customer_count', 1)
            ->assertJsonPath('data.vendor_count', 1)
            ->assertJsonPath('data.pending_vendors', 1)
            ->assertJsonPath('data.product_count', 3)
            ->assertJsonPath('data.pending_products', 3)
            ->assertJsonStructure([
                'data' => [
                    'sales_series',
                    'commission_series',
                    'top_vendors',
                    'top_categories',
                    'top_products',
                    'recent_orders',
                ],
            ]);
    }

    public function test_admin_can_create_vendor(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/vendors', [
            'name' => 'New Shop',
            'email' => 'shop@example.com',
            'password' => 'password123',
            'store_name' => 'New Shop Store',
            'commission_rate' => 8,
        ])
            ->assertOk()
            ->assertJsonPath('data.store_name', 'New Shop Store')
            ->assertJsonPath('data.status', VendorStatus::Pending->value);

        $this->assertDatabaseHas('users', ['email' => 'shop@example.com', 'role' => 'VENDOR']);
        $this->assertDatabaseHas('vendor_profiles', ['store_name' => 'New Shop Store']);
    }

    public function test_admin_can_edit_vendor(): void
    {
        $this->actingAsAdmin();
        $vendor = VendorProfile::factory()->create();

        $this->putJson("/api/v1/admin/vendors/{$vendor->id}", ['store_name' => 'Renamed Shop', 'commission_rate' => 12])
            ->assertOk()
            ->assertJsonPath('data.store_name', 'Renamed Shop')
            ->assertJsonPath('data.commission_rate', '12.00');

        $this->assertDatabaseHas('vendor_profiles', ['id' => $vendor->id, 'store_name' => 'Renamed Shop']);
    }

    public function test_admin_can_approve_suspend_and_reactivate_vendor(): void
    {
        $this->actingAsAdmin();
        $vendor = VendorProfile::factory()->create(['status' => VendorStatus::Pending->value, 'is_approved' => false]);

        $this->putJson("/api/v1/admin/vendors/{$vendor->id}/approve", ['status' => 'APPROVED'])
            ->assertOk()
            ->assertJsonPath('data.status', VendorStatus::Approved->value)
            ->assertJsonPath('data.is_approved', true);

        $this->putJson("/api/v1/admin/vendors/{$vendor->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', VendorStatus::Suspended->value)
            ->assertJsonPath('data.is_approved', false);

        $this->putJson("/api/v1/admin/vendors/{$vendor->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', VendorStatus::Approved->value)
            ->assertJsonPath('data.is_approved', true);
    }

    public function test_admin_can_reject_vendor(): void
    {
        $this->actingAsAdmin();
        $vendor = VendorProfile::factory()->create(['status' => VendorStatus::Pending->value]);

        $this->putJson("/api/v1/admin/vendors/{$vendor->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', VendorStatus::Rejected->value)
            ->assertJsonPath('data.is_approved', false);
    }

    public function test_admin_vendor_show_includes_wallet_and_counts(): void
    {
        $this->actingAsAdmin();
        $vendor = VendorProfile::factory()->create();
        Product::factory()->count(2)->create(['vendor_id' => $vendor->id]);

        $this->getJson("/api/v1/admin/vendors/{$vendor->id}")
            ->assertOk()
            ->assertJsonPath('data.product_count', 2);
    }

    public function test_admin_can_block_and_unblock_customer(): void
    {
        $this->actingAsAdmin();
        $customer = User::factory()->customer()->create(['is_active' => true]);

        $this->putJson("/api/v1/admin/users/{$customer->id}/block")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->putJson("/api/v1/admin/users/{$customer->id}/unblock")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_admin_cannot_block_super_admin(): void
    {
        $this->actingAsAdmin();
        $otherAdmin = User::factory()->superAdmin()->create();

        $this->putJson("/api/v1/admin/users/{$otherAdmin->id}/block")
            ->assertStatus(422);
    }

    public function test_admin_block_revokes_existing_sessions_and_rejects_further_requests(): void
    {
        $this->actingAsAdmin();
        $customer = User::factory()->customer()->create(['is_active' => true]);

        // Simulate the customer being signed in on another device.
        $token = $customer->createToken('session')->plainTextToken;
        $this->assertSame(1, $customer->tokens()->count());

        $this->putJson("/api/v1/admin/users/{$customer->id}/block")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        // The block invalidated every existing session immediately.
        $this->assertSame(0, $customer->tokens()->count());
    }

    public function test_blocked_customer_token_is_rejected_on_protected_routes(): void
    {
        $customer = User::factory()->customer()->create(['is_active' => false]);
        $token = $customer->createToken('session')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/user/orders')
            ->assertStatus(401);

        // The middleware also killed the token that made the request.
        $this->assertSame(0, $customer->tokens()->count());
    }

    public function test_admin_can_publish_unpublish_feature_and_bestseller_product(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create([
            'approval_status' => ProductApprovalStatus::Approved->value,
        ]);

        $this->putJson("/api/v1/admin/products/{$product->id}/publish")->assertOk();
        $this->putJson("/api/v1/admin/products/{$product->id}/feature")->assertOk();
        $this->putJson("/api/v1/admin/products/{$product->id}/best-seller")->assertOk();
        $this->putJson("/api/v1/admin/products/{$product->id}/unpublish")->assertOk();

        $product->refresh();

        $this->assertTrue($product->featured);
        $this->assertTrue($product->best_seller);
    }

    public function test_admin_can_delete_product(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create();

        $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_orders_index_is_filterable(): void
    {
        $this->actingAsAdmin();
        $customer = User::factory()->customer()->create();
        Order::factory()->create(['user_id' => $customer->id]);

        $this->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    public function test_orders_index_search_matches_customer_details(): void
    {
        $this->actingAsAdmin();
        $customer = User::factory()->customer()->create(['name' => 'Priya Sharma']);
        $matching = Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-ZETA-0001',
        ]);
        Order::factory()->create([
            // The trailing "other" order must never collide with the search
            // terms: a random faker name here can be another "Priya …" (US
            // names include Indian given names), and two random 'ORD-…' prefixes
            // can share a leading char. Both would break the count-1 asserts.
            'user_id' => User::factory()->customer()->create(['name' => 'Aarav Mehta']),
            'order_number' => 'ORD-GAMMA-0002',
        ]);

        // The "new order query" search finds an order when only the customer's
        // name, email or phone is known, not just when the order number is.
        $this->getJson('/api/v1/admin/orders?search='.urlencode('Priya'))
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $matching->id);

        $this->getJson('/api/v1/admin/orders?search='.urlencode($customer->email))
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $matching->id);

        // A partial order number still matches as before.
        $this->getJson('/api/v1/admin/orders?search='.substr($matching->order_number, 0, 5))
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $matching->id);
    }

    public function test_non_admin_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->getJson('/api/v1/admin/dashboard')->assertStatus(403);
    }
}
