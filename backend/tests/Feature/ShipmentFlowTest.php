<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Enums\VendorOrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShipmentEvent;
use App\Models\ShipmentWebhook;
use App\Models\ShiprocketSetting;
use App\Models\User;
use App\Models\VendorProfile;
use App\Notifications\ShipmentStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShipmentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected const SHIPROCKET_BASE = 'https://apiv2.shiprocket.in/v1/external';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedShiprocketSettings();
    }

    protected function seedShiprocketSettings(array $overrides = []): ShiprocketSetting
    {
        return ShiprocketSetting::query()->updateOrCreate(['id' => 1], array_merge([
            'enabled' => true,
            'token' => 'test-token',
            'base_url' => self::SHIPROCKET_BASE,
            'default_pickup_location' => 'Primary',
            'webhook_secret' => 'secret-key',
        ], $overrides));
    }

    protected function signedWebhook(string $uri, array $payload, string $secret = 'secret-key')
    {
        $raw = json_encode($payload);

        return $this->call('POST', $uri, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SHIPROCKET_SIGNATURE' => hash_hmac('sha256', (string) $raw, $secret),
        ], $raw);
    }

    protected function placeCotOrder(): array
    {
        $vendor = VendorProfile::factory()->approved()->create();
        $product = Product::factory()->create(['vendor_id' => $vendor->id, 'price' => 200, 'mrp' => 200, 'gst' => 0, 'stock' => 5]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/user/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();

        $this->postJson('/api/v1/user/orders', [
            'address_id' => Address::factory()->create(['user_id' => $customer->id])->id,
            'payment_method' => 'cod',
        ])->assertCreated();

        $order = Order::first();

        return [$vendor, $customer, $order->vendorOrders()->first(), $order];
    }

    protected function fakeShiprocketPipeline(bool $withFailure = false): void
    {
        $adhoc = $withFailure
            ? Http::response(['message' => 'Server error'], 500)
            : Http::response([
                'order_id' => 9001,
                'shipment_id' => [99415931],
                'label_url' => 'https://shiprocket.in/label/98415931.pdf',
            ], 200);

        Http::fake([
            self::SHIPROCKET_BASE.'/shipments/create/adhoc' => $adhoc,
            self::SHIPROCKET_BASE.'/courier/serviceability/check' => Http::response([
                'data' => [
                    'available_courier_companies' => [
                        ['courier_id' => 10, 'courier_name' => 'Delhivery'],
                    ],
                ],
            ], 200),
            self::SHIPROCKET_BASE.'/courier/generate/awb' => Http::response([
                'awb_code' => 'SR123456',
                'courier_id' => 10,
                'courier_name' => 'Delhivery',
            ], 200),
            self::SHIPROCKET_BASE.'/shipments/print/label*' => Http::response([
                'label_url' => 'https://shiprocket.in/label/99415931.pdf',
            ], 200),
        ]);
    }

    public function test_vendor_ships_order_through_full_pipeline(): void
    {
        Event::fake();
        [$vendor, , $vendorOrder, $order] = $this->placeCotOrder();

        $this->fakeShiprocketPipeline();

        Sanctum::actingAs($vendor->user);

        $this->postJson("/api/v1/vendor/orders/{$vendorOrder->id}/ship", ['weight' => 1.5])
            ->assertOk()
            ->assertJsonPath('data.shipment.tracking_number', 'SR123456')
            ->assertJsonPath('data.shipment.status', ShipmentStatus::AwbGenerated->value)
            ->assertJsonPath('data.shipment.courier_name', 'Delhivery')
            ->assertJsonPath('data.shipment.status_label', 'AWB Generated');

        $shipment = $vendorOrder->fresh()->shipment;

        $this->assertNotNull($shipment);
        $this->assertSame(99415931, $shipment->shipment_id);
        $this->assertSame(9001, $shipment->shiprocket_order_id);
        $this->assertSame('https://shiprocket.co/tracking/SR123456', $shipment->tracking_url);
        $this->assertSame('https://shiprocket.in/label/99415931.pdf', $shipment->label_url);
        $this->assertSame('Primary', $shipment->pickup_location);
        $this->assertSame(1.5, (float) $shipment->weight);

        $this->assertSame(VendorOrderStatus::Shipped->value, $vendorOrder->fresh()->status);
        $this->assertSame(OrderStatus::Shipped->value, $order->fresh()->order_status);

        $events = ShipmentEvent::where('shipment_id', $shipment->id)->pluck('status')->all();
        $this->assertContains(ShipmentStatus::Created->value, $events);
        $this->assertContains(ShipmentStatus::AwbGenerated->value, $events);
    }

    public function test_ship_failure_flags_shipment_but_keeps_order_unshipped(): void
    {
        [$vendor, , $vendorOrder, $order] = $this->placeCotOrder();

        $this->fakeShiprocketPipeline(withFailure: true);

        Sanctum::actingAs($vendor->user);

        $this->postJson("/api/v1/vendor/orders/{$vendorOrder->id}/ship")
            ->assertStatus(502);

        $shipment = $vendorOrder->fresh()->shipment;

        $this->assertNotNull($shipment);
        $this->assertSame(ShipmentStatus::Failed, $shipment->status);
        $this->assertNotEmpty($shipment->error_message);

        $this->assertNotSame(VendorOrderStatus::Shipped->value, $vendorOrder->fresh()->status);
        $this->assertNotSame(OrderStatus::Shipped->value, $order->fresh()->order_status);
    }

    public function test_vendor_can_retry_a_failed_shipment(): void
    {
        [$vendor, , $vendorOrder] = $this->placeCotOrder();

        $shipment = $vendorOrder->shipment()->create([
            'order_id' => $vendorOrder->order_id,
            'provider' => 'Shiprocket',
            'status' => ShipmentStatus::Failed->value,
            'error_message' => 'previous failure',
            'retry_count' => 1,
        ]);

        $this->fakeShiprocketPipeline();

        Sanctum::actingAs($vendor->user);

        $this->postJson("/api/v1/vendor/shipments/{$shipment->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.shipment.status', ShipmentStatus::AwbGenerated->value)
            ->assertJsonPath('data.shipment.retry_count', 2);

        $this->assertSame(ShipmentStatus::AwbGenerated, $shipment->fresh()->status);
        $this->assertSame(2, $shipment->fresh()->retry_count);
    }

    public function test_shipping_requires_configured_provider(): void
    {
        $this->seedShiprocketSettings(['enabled' => false]);

        [$vendor, , $vendorOrder] = $this->placeCotOrder();

        Sanctum::actingAs($vendor->user);

        $this->postJson("/api/v1/vendor/orders/{$vendorOrder->id}/ship")
            ->assertStatus(502);

        $this->assertNull($vendorOrder->fresh()->shipment);
    }

    public function test_webhook_updates_shipment_when_matched_by_shipment_id(): void
    {
        [$vendor, $customer, $vendorOrder] = $this->placeCotOrder();

        $vendorOrder->forceFill(['status' => VendorOrderStatus::Shipped->value])->save();

        $shipment = $vendorOrder->shipment()->create([
            'order_id' => $vendorOrder->order_id,
            'provider' => 'Shiprocket',
            'shipment_id' => 99415931,
            'tracking_number' => 'SR123456',
            'status' => ShipmentStatus::AwbGenerated->value,
        ]);

        Notification::fake();

        $payload = [
            'shipment_id' => 99415931,
            'awb_code' => 'SR123456',
            'current_status' => 'OUT FOR DELIVERY',
            'current_timestamp' => '2026-09-02 12:30:00',
            'tracking_data' => [
                [
                    'time' => '2026-09-02 12:30:00',
                    'status' => 'Out for delivery',
                    'location' => 'New Delhi',
                ],
            ],
        ];

        for ($i = 0; $i < 2; $i++) {
            $this->signedWebhook('/api/v1/shipments/webhook', $payload)
                ->assertOk()
                ->assertJsonPath('data.status', 'PROCESSED');
        }

        $shipment->refresh();

        $this->assertSame(ShipmentStatus::OutForDelivery, $shipment->status);
        $this->assertSame('Out for delivery', $shipment->last_event);
        $this->assertSame('OUT FOR DELIVERY', $shipment->external_status);

        Notification::assertSentTo($customer, ShipmentStatusNotification::class);

        $this->assertSame(1, ShipmentEvent::where('shipment_id', $shipment->id)->count());

        $this->assertSame('PROCESSED', ShipmentWebhook::where('awb', 'SR123456')->latest('id')->value('status'));
    }

    public function test_webhook_delivered_closes_order_and_collects_cod(): void
    {
        Event::fake();
        [$vendor, , $vendorOrder, $order] = $this->placeCotOrder();

        $vendorOrder->forceFill(['status' => VendorOrderStatus::Shipped->value])->save();

        $order->forceFill(['payment_status' => PaymentStatus::CodPending->value])->save();

        Payment::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'amount' => $order->total,
            'method' => 'cod',
            'gateway' => 'cod',
            'status' => PaymentStatus::Pending->value,
            'currency' => 'INR',
        ]);

        $shipment = $vendorOrder->shipment()->create([
            'order_id' => $vendorOrder->order_id,
            'provider' => 'Shiprocket',
            'shipment_id' => 99415931,
            'tracking_number' => 'SR123456',
            'status' => ShipmentStatus::InTransit->value,
        ]);

        $this->signedWebhook('/api/v1/shipments/webhook', [
            'shipment_id' => 99415931,
            'awb_code' => 'SR123456',
            'current_status' => 'DELIVERED',
            'current_timestamp' => '2026-09-02 18:00:00',
        ])->assertOk();

        $shipment->refresh();

        $this->assertSame(ShipmentStatus::Delivered, $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertSame(VendorOrderStatus::Delivered->value, $vendorOrder->fresh()->status);
        $this->assertSame(OrderStatus::Delivered->value, $order->fresh()->order_status);
        $this->assertSame(PaymentStatus::Paid->value, $order->fresh()->payment_status);
        $this->assertSame(PaymentStatus::Paid->value, Payment::where('order_id', $order->id)->where('gateway', 'cod')->value('status'));
    }

    public function test_webhook_rejects_when_signature_configured_and_missing(): void
    {
        $this->seedShiprocketSettings(['webhook_secret' => 'secret-key']);

        [$vendor, , $vendorOrder] = $this->placeCotOrder();

        $shipment = $vendorOrder->shipment()->create([
            'order_id' => $vendorOrder->order_id,
            'provider' => 'Shiprocket',
            'shipment_id' => 99415931,
            'tracking_number' => 'SR123456',
            'status' => ShipmentStatus::AwbGenerated->value,
        ]);

        $payload = json_encode([
            'shipment_id' => 99415931,
            'awb_code' => 'SR123456',
            'current_status' => 'IN TRANSIT',
        ]);

        $this->withHeader('X-Shiprocket-Signature', 'bad-signature')
            ->postJson('/api/v1/shipments/webhook', [
                'shipment_id' => 99415931,
                'awb_code' => 'SR123456',
                'current_status' => 'IN TRANSIT',
            ])
            ->assertStatus(401);

        $this->call(
            'POST',
            '/api/v1/shipments/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SHIPROCKET_SIGNATURE' => hash_hmac('sha256', (string) $payload, 'secret-key')],
            $payload,
        )->assertJsonPath('data.status', 'PROCESSED');

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::InTransit, $shipment->status);
        $this->assertSame('PROCESSED', ShipmentWebhook::latest('id')->value('status'));
    }

    public function test_webhook_with_unknown_shipment_is_ignored(): void
    {
        $this->signedWebhook('/api/v1/shipments/webhook', [
            'shipment_id' => 999999,
            'awb_code' => 'UNKNOWN-AWB',
            'current_status' => 'IN TRANSIT',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'IGNORED');

        $this->assertSame('IGNORED', ShipmentWebhook::latest('id')->value('status'));
    }
}
