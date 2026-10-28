<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ShipmentPickupStatus;
use App\Enums\ShipmentStatus;
use App\Enums\VendorOrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocalShippingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.shiprocket.driver' => 'local']);
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

    public function test_vendor_can_create_shipment_locally_without_shiprocket(): void
    {
        Notification::fake();
        Http::preventStrayRequests();

        [$vendor, , $vendorOrder, $order] = $this->placeCotOrder();

        Sanctum::actingAs($vendor->user);

        $this->postJson("/api/v1/vendor/orders/{$vendorOrder->id}/ship", ['weight' => 1.5])
            ->assertOk()
            ->assertJsonPath('data.shipment.provider', 'Local')
            ->assertJsonPath('data.shipment.status', ShipmentStatus::AwbGenerated->value)
            ->assertJsonPath('data.shipment.courier_name', 'Local Delivery');

        $shipment = $vendorOrder->fresh()->shipment;

        $this->assertNotNull($shipment);
        $this->assertStringStartsWith('LOCAL-', $shipment->tracking_number);
        $this->assertNull($shipment->shipment_id);
        $this->assertSame('Local', $shipment->provider);
        $this->assertSame(VendorOrderStatus::Shipped->value, $vendorOrder->fresh()->status);
        $this->assertSame(OrderStatus::Shipped->value, $order->fresh()->order_status);

        $events = ShipmentEvent::where('shipment_id', $shipment->id)->pluck('status')->all();
        $this->assertContains(ShipmentStatus::Created->value, $events);
        $this->assertContains(ShipmentStatus::AwbGenerated->value, $events);
    }

    public function test_vendor_can_schedule_pickup_locally(): void
    {
        Http::preventStrayRequests();
        [$vendor, , $vendorOrder] = $this->placeCotOrder();

        Sanctum::actingAs($vendor->user);
        $this->postJson("/api/v1/vendor/orders/{$vendorOrder->id}/ship")->assertOk();

        $shipment = $vendorOrder->fresh()->shipment;

        $this->postJson("/api/v1/vendor/shipments/{$shipment->id}/pickup")
            ->assertOk()
            ->assertJsonPath('data.shipment.pickup_status', ShipmentPickupStatus::Scheduled->value);

        $this->assertSame(ShipmentPickupStatus::Scheduled, $shipment->fresh()->pickup_status);
        $this->assertNotNull($shipment->fresh()->pickup_requested_at);
    }
}
