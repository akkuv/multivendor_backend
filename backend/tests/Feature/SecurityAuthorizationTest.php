<?php

namespace Tests\Feature;

use App\Models\CommissionSetting;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function approvedVendor(): VendorProfile
    {
        return VendorProfile::factory()->approved()->create();
    }

    private function walletFor(User $user): Wallet
    {
        return $user->wallet()->firstOrCreate(['user_id' => $user->id], ['available_balance' => 0]);
    }

    public function test_unauthenticated_request_to_protected_route_is_rejected(): void
    {
        $this->getJson('/api/v1/user/cart')->assertStatus(401);
        $this->getJson('/api/v1/admin/orders')->assertStatus(401);
        $this->getJson('/api/v1/vendor/dashboard')->assertStatus(401);
    }

    public function test_customer_cannot_access_admin_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->getJson('/api/v1/admin/orders')->assertStatus(403);
        $this->getJson('/api/v1/admin/email-templates')->assertStatus(403);
        $this->getJson('/api/v1/admin/audit-logs')->assertStatus(403);
    }

    public function test_customer_cannot_access_vendor_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->getJson('/api/v1/vendor/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/vendor/wallet')->assertStatus(403);
    }

    public function test_vendor_cannot_access_admin_endpoints(): void
    {
        $vendor = $this->approvedVendor();
        Sanctum::actingAs($vendor->user);

        $this->getJson('/api/v1/admin/orders')->assertStatus(403);
        $this->getJson('/api/v1/admin/email-templates')->assertStatus(403);
    }

    public function test_admin_cannot_access_vendor_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson('/api/v1/vendor/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/vendor/wallet')->assertStatus(403);
    }

    public function test_vendor_cannot_access_customer_only_endpoints(): void
    {
        $vendor = $this->approvedVendor();
        Sanctum::actingAs($vendor->user);

        $this->getJson('/api/v1/user/cart')->assertStatus(403);
        $this->getJson('/api/v1/user/orders')->assertStatus(403);
    }

    public function test_admin_cannot_access_customer_only_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson('/api/v1/user/wallet')->assertStatus(403);
    }

    public function test_customer_cannot_view_another_customers_wallet_transaction(): void
    {
        [$alice, $bob] = User::factory()->customer()->count(2)->create();

        $this->walletFor($alice);
        $wallet = $this->walletFor($bob);

        $transaction = $wallet->transactions()->create([
            'type' => 'CREDIT',
            'amount' => 100,
            'balance_before' => 0,
            'balance_after' => 100,
            'status' => 'COMPLETED',
            'description' => 'Test',
        ]);

        Sanctum::actingAs($alice);

        $this->getJson("/api/v1/user/wallet/transactions/{$transaction->id}")->assertStatus(404);
    }

    public function test_vendor_cannot_update_another_vendors_product(): void
    {
        $vendorA = $this->approvedVendor();
        $vendorB = $this->approvedVendor();

        $product = Product::factory()->create(['vendor_id' => $vendorB->id]);

        Sanctum::actingAs($vendorA->user);

        $this->putJson("/api/v1/vendor/products/{$product->id}", ['price' => 999])->assertStatus(404);
    }

    public function test_customer_cannot_change_product_prices_via_admin(): void
    {
        $product = Product::factory()->create();

        Sanctum::actingAs(User::factory()->customer()->create());

        $this->putJson("/api/v1/vendor/products/{$product->id}", ['price' => 1])->assertStatus(403);
        $this->putJson("/api/v1/admin/products/{$product->id}/publish")->assertStatus(403);
    }

    public function test_customer_cannot_change_commission_settings(): void
    {
        $setting = CommissionSetting::factory()->create();

        Sanctum::actingAs(User::factory()->customer()->create());

        $this->putJson("/api/v1/admin/commissions/{$setting->id}", [
            'rate' => 99,
            'is_active' => true,
        ])->assertStatus(403);
    }

    public function test_vendor_cannot_change_commission_settings(): void
    {
        $setting = CommissionSetting::factory()->create();
        $vendor = $this->approvedVendor();

        Sanctum::actingAs($vendor->user);

        $this->putJson("/api/v1/admin/commissions/{$setting->id}", [
            'rate' => 99,
            'is_active' => true,
        ])->assertStatus(403);
    }

    public function test_customer_cannot_alter_payment_state_or_manage_coupons(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->postJson('/api/v1/admin/coupons', ['code' => 'SAVE10'])->assertStatus(403);
        $this->postJson('/api/v1/admin/settlements/run')->assertStatus(403);
        $this->putJson('/api/v1/admin/payment-settings', ['key' => 'old'])->assertStatus(403);
    }

    public function test_vendor_cannot_alter_payment_state_or_manage_coupons(): void
    {
        $vendor = $this->approvedVendor();

        Sanctum::actingAs($vendor->user);

        $this->postJson('/api/v1/admin/coupons', ['code' => 'SAVE10'])->assertStatus(403);
        $this->postJson('/api/v1/admin/settlements/run')->assertStatus(403);
        $this->putJson('/api/v1/admin/payment-settings', ['key' => 'old'])->assertStatus(403);
    }
}
