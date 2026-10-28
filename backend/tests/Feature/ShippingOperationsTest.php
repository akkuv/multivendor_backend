<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Enums\VendorOrderStatus;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\ShiprocketSetting;
use App\Models\User;
use App\Models\VendorOrder;
use App\Models\VendorProfile;
use App\Services\Shipping\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShippingOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected const SHIPROCKET_BASE = 'https://apiv2.shiprocket.in/v1/external';

    protected function setUp(): void
    {
        parent::setUp();

        ShiprocketSetting::query()->updateOrCreate(['id' => 1], [
            'enabled' => true,
            'token' => 'test-token',
            'base_url' => self::SHIPROCKET_BASE,
            'default_pickup_location' => 'Primary',
        ]);
    }

    /* ----------------------------------------------------------------- */
    /* Courier rate shopping */
    /* ----------------------------------------------------------------- */

    public function test_vendor_can_shop_courier_rates_for_own_order(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $vendorOrder = $this->shippableVendorOrder();

        Http::fake([
            self::SHIPROCKET_BASE.'/courier/serviceability/check' => Http::response([
                'recommended_courier' => ['courier_id' => 3, 'courier_name' => 'Delhivery'],
                'available_courier_companies' => [
                    ['courier_id' => 5, 'courier_name' => 'Blue Dart', 'rate' => 180, 'cod_available' => true, 'delivery_time' => 2],
                    ['courier_id' => 3, 'courier_name' => 'Delhivery', 'rate' => 120, 'cod_available' => true, 'delivery_time' => 4],
                ],
            ], 200),
        ]);

        Sanctum::actingAs($vendorOrder->vendor->user);

        $this->getJson("/api/v1/vendor/orders/{$vendorOrder->id}/couriers?weight=1.5")
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.recommended_courier_id', 3)
            ->assertJsonCount(2, 'data.couriers')
            // Sorted cheapest first.
            ->assertJsonPath('data.couriers.0.courier_name', 'Delhivery')
            ->assertJsonPath('data.couriers.0.rate', 120)
            ->assertJsonPath('data.couriers.0.is_recommended', true)
            ->assertJsonPath('data.couriers.1.courier_name', 'Blue Dart');
    }

    public function test_rate_shopping_reports_when_no_courier_is_serviceable(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $vendorOrder = $this->shippableVendorOrder();

        Http::fake([
            self::SHIPROCKET_BASE.'/courier/serviceability/check' => Http::response([
                'message' => 'No courier available for this pincode.',
                'data' => ['available_courier_companies' => []],
            ], 200),
        ]);

        Sanctum::actingAs($vendorOrder->vendor->user);

        $this->getJson("/api/v1/vendor/orders/{$vendorOrder->id}/couriers")
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.couriers', []);
    }

    public function test_rate_shopping_is_scoped_to_the_owning_vendor(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $vendorOrder = $this->shippableVendorOrder();

        Http::fake([
            self::SHIPROCKET_BASE.'/courier/serviceability/check' => Http::response([], 200),
        ]);

        Sanctum::actingAs(VendorProfile::factory()->approved()->create()->user);

        $this->getJson("/api/v1/vendor/orders/{$vendorOrder->id}/couriers")->assertStatus(404);
    }

    public function test_rate_shopping_falls_back_to_local_delivery_in_local_driver(): void
    {
        config(['services.shiprocket.driver' => 'local']);

        $vendorOrder = $this->shippableVendorOrder();

        Http::fake();

        Sanctum::actingAs($vendorOrder->vendor->user);

        $this->getJson("/api/v1/vendor/orders/{$vendorOrder->id}/couriers")
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.couriers.0.courier_name', 'Local Delivery')
            ->assertJsonPath('data.couriers.0.rate', 0);

        Http::assertNothingSent();
    }

    public function test_shipping_honours_an_explicitly_chosen_courier(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $vendorOrder = $this->shippableVendorOrder();

        Http::fake([
            self::SHIPROCKET_BASE.'/shipments/create/adhoc' => Http::response([
                'order_id' => 88_888_88,
                'shipment_id' => [44_444_44],
                'cost_to_pay' => 150,
            ], 200),
            self::SHIPROCKET_BASE.'/courier/generate/awb' => Http::response([
                'awb_code' => 'SRX999',
                'courier_id' => 9,
                'courier_name' => 'Ecom Express',
            ], 200),
            self::SHIPROCKET_BASE.'/shipments/print/label*' => Http::response([
                'label_url' => 'https://shiprocket.in/label.pdf',
            ], 200),
        ]);

        Sanctum::actingAs($vendorOrder->vendor->user);

        $this->postJson("/api/v1/vendor/orders/{$vendorOrder->id}/ship", [
            'weight' => 1.2,
            'courier_id' => 9,
        ])->assertOk()->assertJsonPath('data.shipment.courier_name', 'Ecom Express');

        // The recommendation call must be skipped entirely when a courier is chosen.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'serviceability'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'courier/generate/awb')
            && $request['courier_id'] === 9);
    }

    /* ----------------------------------------------------------------- */
    /* Labels */
    /* ----------------------------------------------------------------- */

    public function test_label_endpoint_returns_the_cached_url_without_calling_shiprocket(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $shipment = Shipment::factory()->onShiprocket()->awbGenerated()->create([
            'label_url' => 'https://shiprocket.in/cached.pdf',
        ]);

        Http::fake();

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson("/api/v1/admin/shipments/{$shipment->id}/label")
            ->assertOk()
            ->assertJsonPath('data.label_url', 'https://shiprocket.in/cached.pdf');

        Http::assertNothingSent();
    }

    public function test_label_endpoint_fails_loudly_when_no_label_can_be_produced(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $shipment = Shipment::factory()->awbGenerated()->create(['label_url' => null]);

        Http::fake();

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        // Never "succeed" with a null URL — the vendor would have nothing to open.
        $this->getJson("/api/v1/admin/shipments/{$shipment->id}/label")
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null);

        Http::assertNothingSent();
    }

    public function test_label_endpoint_reports_an_empty_shiprocket_response(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $shipment = Shipment::factory()->onShiprocket()->awbGenerated()->create(['label_url' => null]);

        Http::fake([
            self::SHIPROCKET_BASE.'/shipments/print/label*' => Http::response(['label_url' => ''], 200),
        ]);

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson("/api/v1/admin/shipments/{$shipment->id}/label")
            ->assertStatus(502)
            ->assertJsonPath('success', false);

        $this->assertNull($shipment->fresh()->label_url);
    }

    public function test_label_endpoint_fails_loudly_in_local_driver_mode(): void
    {
        config(['services.shiprocket.driver' => 'local']);

        $shipment = Shipment::factory()->onShiprocket()->awbGenerated()->create(['label_url' => null]);

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson("/api/v1/admin/shipments/{$shipment->id}/label")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_label_endpoint_generates_and_persists_a_missing_label(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $shipment = Shipment::factory()->onShiprocket()->awbGenerated()->create([
            'label_url' => null,
        ]);

        Http::fake([
            self::SHIPROCKET_BASE.'/shipments/print/label*' => Http::response([
                'label_url' => 'https://shiprocket.in/fresh.pdf',
            ], 200),
        ]);

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson("/api/v1/admin/shipments/{$shipment->id}/label")
            ->assertOk()
            ->assertJsonPath('data.label_url', 'https://shiprocket.in/fresh.pdf');

        $this->assertSame('https://shiprocket.in/fresh.pdf', $shipment->fresh()->label_url);
    }

    public function test_vendor_label_endpoint_rejects_another_vendors_shipment(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $vendorOrder = $this->shippableVendorOrder();
        $shipment = Shipment::factory()->onShiprocket()->awbGenerated()->create([
            'vendor_order_id' => $vendorOrder->id,
        ]);

        Http::fake();

        Sanctum::actingAs(VendorProfile::factory()->approved()->create()->user);

        $this->getJson("/api/v1/vendor/shipments/{$shipment->id}/label")->assertStatus(404);
    }

    /* ----------------------------------------------------------------- */
    /* Manifest */
    /* ----------------------------------------------------------------- */

    public function test_manifest_is_generated_and_cached_on_every_shipment(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $a = Shipment::factory()->onShiprocket()->awbGenerated()->create();
        $b = Shipment::factory()->onShiprocket()->awbGenerated()->create();

        Http::fake([
            self::SHIPROCKET_BASE.'/shipments/print/manifest*' => Http::response([
                'manifest_url' => 'https://shiprocket.in/manifest.pdf',
            ], 200),
        ]);

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/v1/admin/shipments/manifest', [
            'shipment_ids' => [$a->id, $b->id],
        ])->assertOk()->assertJsonPath('data.manifest_url', 'https://shiprocket.in/manifest.pdf');

        $this->assertSame('https://shiprocket.in/manifest.pdf', $a->fresh()->manifest_url);
        $this->assertSame('https://shiprocket.in/manifest.pdf', $b->fresh()->manifest_url);

        Http::assertSent(function ($request) use ($a, $b): bool {
            $ids = collect($request['shipment_ids'] ?? [])->sort()->values()->all();

            return $ids === collect([$a->shipment_id, $b->shipment_id])->sort()->values()->all();
        });
    }

    public function test_manifest_fails_loudly_in_local_driver_mode(): void
    {
        config(['services.shiprocket.driver' => 'local']);

        $shipment = Shipment::factory()->onShiprocket()->create();

        Http::fake();

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        // A "successful" null URL would silently clear the vendor's selection.
        $this->postJson('/api/v1/admin/shipments/manifest', ['shipment_ids' => [$shipment->id]])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null);

        Http::assertNothingSent();
    }

    public function test_vendor_manifest_reports_the_reason_without_a_generic_prefix(): void
    {
        config(['services.shiprocket.driver' => 'local']);

        $vendorOrder = $this->shippableVendorOrder();
        $shipment = Shipment::factory()->onShiprocket()->create(['vendor_order_id' => $vendorOrder->id]);

        Http::fake();

        Sanctum::actingAs($vendorOrder->vendor->user);

        $this->postJson('/api/v1/vendor/shipments/manifest', ['shipment_ids' => [$shipment->id]])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Shipping is running in local mode, so no pickup manifest can be generated.'
            );

        Http::assertNothingSent();
    }

    public function test_manifest_fails_loudly_when_shiprocket_returns_no_url(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $shipment = Shipment::factory()->onShiprocket()->create();

        Http::fake([
            self::SHIPROCKET_BASE.'/shipments/print/manifest*' => Http::response(['manifest_url' => ''], 200),
        ]);

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/v1/admin/shipments/manifest', ['shipment_ids' => [$shipment->id]])
            ->assertStatus(502)
            ->assertJsonPath('success', false);

        $this->assertNull($shipment->fresh()->manifest_url);
    }

    public function test_manifest_requires_at_least_one_shipment(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/v1/admin/shipments/manifest', ['shipment_ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('shipment_ids');
    }

    public function test_manifest_rejects_shipments_not_yet_on_shiprocket(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $local = Shipment::factory()->create(['shipment_id' => null]);

        Http::fake();

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/v1/admin/shipments/manifest', ['shipment_ids' => [$local->id]])
            ->assertStatus(422);
    }

    public function test_vendor_manifest_only_includes_their_own_shipments(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $vendorOrder = $this->shippableVendorOrder();
        $own = Shipment::factory()->onShiprocket()->awbGenerated()->create([
            'vendor_order_id' => $vendorOrder->id,
        ]);
        $foreign = Shipment::factory()->onShiprocket()->awbGenerated()->create();

        Http::fake([
            self::SHIPROCKET_BASE.'/shipments/print/manifest*' => Http::response([
                'manifest_url' => 'https://shiprocket.in/manifest.pdf',
            ], 200),
        ]);

        Sanctum::actingAs($vendorOrder->vendor->user);

        $this->postJson('/api/v1/vendor/shipments/manifest', [
            'shipment_ids' => [$own->id, $foreign->id],
        ])->assertOk();

        Http::assertSent(fn ($request) => $request['shipment_ids'] === [$own->shipment_id]);
        $this->assertNull($foreign->fresh()->manifest_url);
    }

    /* ----------------------------------------------------------------- */
    /* Cancellation */
    /* ----------------------------------------------------------------- */

    public function test_admin_can_cancel_an_undelivered_shipment(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $shipment = Shipment::factory()->onShiprocket()->awbGenerated()->create();

        Http::fake([
            self::SHIPROCKET_BASE.'/shipments/cancel' => Http::response(['success' => true], 200),
        ]);

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson("/api/v1/admin/shipments/{$shipment->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.shipment.status', ShipmentStatus::Cancelled->value);

        $this->assertSame(ShipmentStatus::Cancelled, $shipment->fresh()->status);
        Http::assertSent(fn ($request) => $request['ids'] === [(int) $shipment->shipment_id]);
    }

    public function test_delivered_shipments_cannot_be_cancelled(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $shipment = Shipment::factory()->onShiprocket()->delivered()->create();

        Http::fake();

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson("/api/v1/admin/shipments/{$shipment->id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        Http::assertNothingSent();
    }

    public function test_vendor_cannot_cancel_another_vendors_shipment(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $vendorOrder = $this->shippableVendorOrder();
        $shipment = Shipment::factory()->onShiprocket()->awbGenerated()->create([
            'vendor_order_id' => $vendorOrder->id,
        ]);

        Http::fake();

        Sanctum::actingAs(VendorProfile::factory()->approved()->create()->user);

        $this->postJson("/api/v1/vendor/shipments/{$shipment->id}/cancel")->assertStatus(404);
    }

    /* ----------------------------------------------------------------- */
    /* Pickup picker + tracking fallback */
    /* ----------------------------------------------------------------- */

    public function test_pickup_response_persists_the_assigned_picker(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $shipment = Shipment::factory()->onShiprocket()->awbGenerated()->create();

        Http::fake([
            self::SHIPROCKET_BASE.'/shipments/pickup' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'shipment_id' => $shipment->shipment_id,
                        'picker_id' => 771,
                        'picker_name' => 'Rahul Kumar',
                        'picker_phone' => '9876543210',
                    ],
                ],
            ], 200),
        ]);

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson("/api/v1/admin/shipments/{$shipment->id}/pickup", [
            'pickup_date' => '2026-09-20',
            'pickup_time' => '10:00',
        ])->assertOk();

        $this->assertSame('Rahul Kumar', $shipment->fresh()->picker);
    }

    public function test_tracking_falls_back_to_the_shiprocket_order_when_awb_lookup_fails(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $shipment = Shipment::factory()->onShiprocket()->awbGenerated()->create([
            'tracking_number' => 'SR-DEAD-1',
            'shiprocket_order_id' => 99112233,
        ]);

        Http::fake([
            self::SHIPROCKET_BASE.'/courier/track/awb/*' => Http::response(['message' => 'not found'], 400),
            self::SHIPROCKET_BASE.'/courier/track*' => Http::response([
                'tracking_data' => [['status' => 'IN TRANSIT']],
            ], 200),
        ]);

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->assertSame(
            ['tracking_data' => [['status' => 'IN TRANSIT']]],
            $this->app->make(ShippingService::class)->track($shipment),
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), 'courier/track')
            && ! str_contains($request->url(), 'awb')
            && $request['order_id'] === '99112233');
    }

    /* ----------------------------------------------------------------- */
    /* Shiprocket settings: live pickup locations */
    /* ----------------------------------------------------------------- */

    public function test_settings_expose_the_webhook_url_an_admin_must_register(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson('/api/v1/admin/shiprockets/settings')
            ->assertOk()
            ->assertJsonPath('data.webhook_url', url('/api/v1/shipments/webhook'))
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.webhook_secret');
    }

    public function test_settings_update_keeps_existing_secrets_when_they_are_omitted(): void
    {
        ShiprocketSetting::query()->updateOrCreate(['id' => 1], [
            'enabled' => true,
            'token' => 'keep-me',
            'password' => 'keep-this-too',
            'base_url' => self::SHIPROCKET_BASE,
        ]);

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->putJson('/api/v1/admin/shiprockets/settings', [
            'enabled' => false,
            'default_pickup_location' => 'Primary',
            'pickup_locations' => ['Primary', 'Secondary'],
        ])
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.token_set', true)
            ->assertJsonPath('data.password_set', true);

        $setting = ShiprocketSetting::query()->firstOrFail();
        $this->assertSame('keep-me', $setting->token);
        $this->assertSame('keep-this-too', $setting->password);
        $this->assertSame(['Primary', 'Secondary'], $setting->pickup_locations);
    }

    public function test_admin_can_fetch_pickup_locations_from_shiprocket(): void
    {
        Http::fake([
            self::SHIPROCKET_BASE.'/pickup/list' => Http::response([
                'success' => true,
                'data' => [
                    ['pickup_location_id' => 1, 'pickup_location' => 'Primary'],
                    ['pickup_location_id' => 2, 'pickup_location' => 'Secondary'],
                ],
            ], 200),
        ]);

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson('/api/v1/admin/shiprockets/pickup-locations')
            ->assertOk()
            ->assertJsonCount(2, 'data.pickup_locations')
            ->assertJsonPath('data.pickup_locations.0.pickup_location', 'Primary');
    }

    public function test_pickup_locations_endpoint_fails_cleanly_without_credentials(): void
    {
        ShiprocketSetting::query()->updateOrCreate(['id' => 1], [
            'enabled' => true,
            'token' => null,
            'base_url' => self::SHIPROCKET_BASE,
        ]);

        Http::fake([
            self::SHIPROCKET_BASE.'/auth/login' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson('/api/v1/admin/shiprockets/pickup-locations')
            ->assertStatus(502)
            ->assertJsonPath('success', false);
    }

    public function test_customer_tracking_returns_live_courier_data_and_stored_events(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create(['user_id' => $customer->id]);
        $shipment = Shipment::factory()->onShiprocket()->awbGenerated()->create([
            'order_id' => $order->id,
            'tracking_number' => 'AWB123456789',
        ]);

        ShipmentEvent::factory()->create([
            'shipment_id' => $shipment->id,
            'status' => ShipmentStatus::PickedUp->value,
            'occurred_at' => now()->subDays(2),
        ]);
        ShipmentEvent::factory()->create([
            'shipment_id' => $shipment->id,
            'status' => ShipmentStatus::InTransit->value,
            'occurred_at' => now()->subDay(),
        ]);

        Http::fake([
            self::SHIPROCKET_BASE.'/courier/track/awb/AWB123456789' => Http::response([
                'awb' => 'AWB123456789',
                'is_current' => 1,
                'tracking_data' => [
                    ['status' => 'IN_TRANSIT', 'status_date' => '2026-09-29 10:00:00', 'location' => 'Bhiwandi'],
                ],
            ], 200),
        ]);

        Sanctum::actingAs($customer);

        // Events are returned oldest first, matching the relation's ordering.
        $this->getJson("/api/v1/user/track/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.shipment.tracking_number', 'AWB123456789')
            ->assertJsonPath('data.tracking.awb', 'AWB123456789')
            ->assertJsonPath('data.tracking_notice', null)
            ->assertJsonCount(2, 'data.shipment.events')
            ->assertJsonPath('data.shipment.events.0.status', 'PICKED_UP')
            ->assertJsonPath('data.shipment.events.1.status', 'IN_TRANSIT')
            ->assertJsonPath('data.shipment.status_label', 'AWB Generated');
    }

    public function test_customer_tracking_degrades_to_stored_status_when_the_courier_is_unreachable(): void
    {
        config(['services.shiprocket.driver' => 'shiprocket']);

        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create(['user_id' => $customer->id]);
        $shipment = Shipment::factory()->awbGenerated()->create([
            'order_id' => $order->id,
            'tracking_number' => 'AWB000000000',
        ]);

        ShipmentEvent::factory()->create([
            'shipment_id' => $shipment->id,
            'status' => ShipmentStatus::PickedUp->value,
            'occurred_at' => now()->subHours(6),
        ]);

        Http::fake([
            self::SHIPROCKET_BASE.'/courier/track/awb/*' => Http::response(['message' => 'Server Error'], 500),
        ]);

        Sanctum::actingAs($customer);

        // The customer must still see where their parcel is rather than an error.
        $this->getJson("/api/v1/user/track/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.tracking', null)
            ->assertJsonPath('data.shipment.status', 'AWB_GENERATED')
            ->assertJsonCount(1, 'data.shipment.events')
            ->assertJsonPath('data.shipment.events.0.status', 'PICKED_UP');

        $this->assertIsString(
            $this->getJson("/api/v1/user/track/{$order->id}")->json('data.tracking_notice')
        );
    }

    public function test_customer_cannot_track_another_customers_order(): void
    {
        $order = Order::factory()->create(['user_id' => User::factory()->customer()->create()]);

        Shipment::factory()->awbGenerated()->create(['order_id' => $order->id]);

        Sanctum::actingAs(User::factory()->customer()->create());

        $this->getJson("/api/v1/user/track/{$order->id}")
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_tracking_is_rejected_for_orders_without_a_shipment(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create(['user_id' => $customer->id]);

        Sanctum::actingAs($customer);

        $this->getJson("/api/v1/user/track/{$order->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'No shipment found for this order yet.');
    }

    protected function shippableVendorOrder(): VendorOrder
    {
        $vendor = VendorProfile::factory()->approved()->create(['pincode' => '110001']);
        $order = Order::factory()->create(['user_id' => User::factory()->customer()->create()]);

        OrderAddress::factory()->create([
            'order_id' => $order->id,
            'type' => 'shipping',
            'pincode' => '400001',
        ]);

        return VendorOrder::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Pending->value,
        ]);
    }
}
