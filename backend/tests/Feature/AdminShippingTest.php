<?php

namespace Tests\Feature;

use App\Enums\ShipmentPickupStatus;
use App\Enums\ShipmentStatus;
use App\Enums\VendorOrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShiprocketSetting;
use App\Models\User;
use App\Models\VendorOrder;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminShippingTest extends TestCase
{
    use RefreshDatabase;

    protected const SHIPROCKET_BASE = 'https://apiv2.shiprocket.in/v1/external';

    public function test_settings_are_masked_and_status_fields_reported(): void
    {
        ShiprocketSetting::create([
            'enabled' => true,
            'environment' => 'test',
            'email' => 'ops@example.com',
            'password' => 'super-secret',
            'token' => 'abc-123',
            'webhook_secret' => 'wh-secret',
            'default_pickup_location' => 'Primary',
            'pickup_locations' => ['Primary', 'Secondary'],
        ]);

        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/shiprockets/settings')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.password_set', true)
            ->assertJsonPath('data.token_set', true)
            ->assertJsonPath('data.webhook_secret_set', true)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.webhook_secret')
            ->assertJsonCount(2, 'data.pickup_locations');
    }

    public function test_settings_update_never_overwrites_secrets_with_blanks(): void
    {
        ShiprocketSetting::create([
            'enabled' => false,
            'email' => 'ops@example.com',
            'password' => 'kept-secret',
            'token' => 'kept-token',
        ]);

        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/shiprockets/settings', [
            'enabled' => true,
            'email' => 'new@example.com',
            'password' => '',
            'token' => '',
            'default_pickup_location' => 'Primary',
        ])->assertOk();

        $settings = ShiprocketSetting::first();

        $this->assertTrue($settings->enabled);
        $this->assertSame('new@example.com', $settings->email);
        $this->assertSame('kept-secret', $settings->password);
        $this->assertSame('kept-token', $settings->token);
        $this->assertTrue($settings->hasPassword());
        $this->assertTrue($settings->hasToken());
    }

    public function test_token_update_actually_replaces_a_secret(): void
    {
        ShiprocketSetting::create([
            'enabled' => true,
            'token' => 'old-token',
        ]);

        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/shiprockets/settings', ['token' => 'brand-new-token'])->assertOk();

        $this->assertSame('brand-new-token', ShiprocketSetting::first()->token);
    }

    public function test_connection_test_relies_on_configured_token(): void
    {
        ShiprocketSetting::create([
            'enabled' => true,
            'token' => 'configured-token',
            'base_url' => self::SHIPROCKET_BASE,
        ]);

        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/shiprockets/test')
            ->assertOk()
            ->assertJsonPath('data.token_generated', true);
    }

    public function test_connection_test_reports_failure_when_credentials_missing(): void
    {
        ShiprocketSetting::create(['enabled' => true, 'base_url' => self::SHIPROCKET_BASE]);

        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/shiprockets/test')
            ->assertStatus(502)
            ->assertJsonPath('success', false);
    }

    public function test_only_super_admin_can_manage_settings(): void
    {
        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/admin/shiprockets/settings')->assertStatus(403);
    }

    public function test_pickup_scheduling_marks_shipment_scheduled(): void
    {
        ShiprocketSetting::create([
            'enabled' => true,
            'token' => 'test-token',
            'base_url' => self::SHIPROCKET_BASE,
            'default_pickup_location' => 'Primary',
        ]);

        $vendor = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 200, 'mrp' => 200, 'gst' => 0, 'stock' => 5]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $this->postJson('/api/v1/user/orders', [
            'address_id' => Address::factory()->create(['user_id' => $customer->id])->id,
            'payment_method' => 'cod',
        ])->assertCreated();

        $order = Order::first();
        $vendorOrder = $order->vendorOrders()->first();

        $vendorOrder->forceFill(['status' => VendorOrderStatus::Shipped->value])->save();

        $shipment = $vendorOrder->shipment()->create([
            'order_id' => $order->id,
            'provider' => 'Shiprocket',
            'shipment_id' => 99415931,
            'tracking_number' => 'SR123456',
            'status' => ShipmentStatus::AwbGenerated->value,
        ]);

        Http::fake([
            self::SHIPROCKET_BASE.'/shipments/pickup' => Http::response(['success' => true], 200),
        ]);

        Sanctum::actingAs($vendor->user);

        $this->postJson("/api/v1/vendor/shipments/{$shipment->id}/pickup", [
            'pickup_date' => '2026-09-10',
            'pickup_time' => '10:00',
        ])
            ->assertOk()
            ->assertJsonPath('data.shipment.pickup_status', 'SCHEDULED');

        $this->assertSame(ShipmentPickupStatus::Scheduled, $shipment->fresh()->pickup_status);
        $this->assertNotNull($shipment->fresh()->pickup_requested_at);
    }

    public function test_admin_lists_shipments_and_filters_by_status(): void
    {
        Shipment::factory()->count(2)->create(['status' => ShipmentStatus::AwbGenerated->value]);
        Shipment::factory()->create(['status' => ShipmentStatus::Delivered->value]);

        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/shipments')->assertOk()->assertJsonCount(3, 'data.data');

        $this->getJson('/api/v1/admin/shipments?status=DELIVERED')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    public function test_vendor_shipment_index_is_scoped_to_own_orders(): void
    {
        $vendorA = VendorProfile::factory()->approved()->create();
        $vendorB = VendorProfile::factory()->approved()->create();

        $order = Order::factory()->create(['user_id' => User::factory()->customer()->create()]);
        $vendorOrder = VendorOrder::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => $vendorA->id,
            'status' => VendorOrderStatus::Shipped->value,
        ]);

        $shipmentA = Shipment::factory()->create([
            'order_id' => $order->id,
            'vendor_order_id' => $vendorOrder->id,
            'status' => ShipmentStatus::AwbGenerated->value,
        ]);

        Sanctum::actingAs($vendorB->user);

        $this->getJson('/api/v1/vendor/shipments')->assertOk()->assertJsonCount(0, 'data.data');
        $this->getJson("/api/v1/vendor/shipments/{$shipmentA->id}")->assertStatus(404);

        Sanctum::actingAs($vendorA->user);

        $this->getJson('/api/v1/vendor/shipments')->assertOk()->assertJsonCount(1, 'data.data');
        $this->getJson("/api/v1/vendor/shipments/{$shipmentA->id}")->assertOk();
    }

    public function test_admin_retry_rejects_non_failed_shipments(): void
    {
        $shipment = Shipment::factory()->create(['status' => ShipmentStatus::AwbGenerated->value]);

        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/shipments/{$shipment->id}/retry")->assertStatus(422);
    }
}
