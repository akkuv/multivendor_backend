<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\Shipment;
use App\Models\User;
use App\Models\VendorOrder;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the ownership rules centralized in app/Policies. The policies replace
 * per-controller `vendor_id !== ...` checks, so this is the single place that
 * pins down who may read and mutate a resource.
 */
class ResourcePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function approvedVendor(): VendorProfile
    {
        return VendorProfile::factory()->approved()->create();
    }

    private function vendorOrderFor(VendorProfile $vendor): VendorOrder
    {
        $order = Order::factory()->create();

        return VendorOrder::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
        ]);
    }

    public function test_a_vendor_may_manage_only_their_own_products(): void
    {
        $vendorA = $this->approvedVendor();
        $vendorB = $this->approvedVendor();
        $product = Product::factory()->create(['vendor_id' => $vendorB->id]);

        $gate = Gate::forUser($vendorA->user);

        $this->assertFalse($gate->allows('view', $product));
        $this->assertFalse($gate->allows('update', $product));
        $this->assertFalse($gate->allows('delete', $product));

        $ownProduct = Product::factory()->create(['vendor_id' => $vendorA->id]);
        $ownGate = Gate::forUser($vendorA->user);

        $this->assertTrue($ownGate->allows('update', $ownProduct));
        $this->assertTrue($ownGate->allows('delete', $ownProduct));
    }

    public function test_a_customer_may_not_reach_the_vendor_product_policy(): void
    {
        $product = Product::factory()->create();

        $gate = Gate::forUser(User::factory()->customer()->create());

        $this->assertFalse($gate->allows('view', $product));
        $this->assertFalse($gate->allows('create', Product::class));
    }

    public function test_a_customer_may_view_only_their_own_order(): void
    {
        [$alice, $bob] = User::factory()->customer()->count(2)->create();
        $order = Order::factory()->create(['user_id' => $bob->id]);

        $this->assertFalse(Gate::forUser($alice)->allows('view', $order));
        $this->assertFalse(Gate::forUser($alice)->allows('cancel', $order));
        $this->assertTrue(Gate::forUser($bob)->allows('view', $order));
        $this->assertTrue(Gate::forUser($bob)->allows('cancel', $order));
    }

    public function test_a_vendor_may_manage_only_their_own_vendor_orders(): void
    {
        $vendorA = $this->approvedVendor();
        $vendorB = $this->approvedVendor();
        $vendorOrder = $this->vendorOrderFor($vendorB);

        $this->assertFalse(Gate::forUser($vendorA->user)->allows('view', $vendorOrder));
        $this->assertFalse(Gate::forUser($vendorA->user)->allows('update', $vendorOrder));

        $ownOrder = $this->vendorOrderFor($vendorA);

        $this->assertTrue(Gate::forUser($vendorA->user)->allows('view', $ownOrder));
        $this->assertTrue(Gate::forUser($vendorA->user)->allows('update', $ownOrder));
    }

    public function test_a_vendor_may_manage_only_their_own_shipments(): void
    {
        $vendorA = $this->approvedVendor();
        $vendorB = $this->approvedVendor();

        $otherShipment = Shipment::factory()->create([
            'vendor_order_id' => $this->vendorOrderFor($vendorB)->id,
        ]);

        $this->assertFalse(Gate::forUser($vendorA->user)->allows('view', $otherShipment));
        $this->assertFalse(Gate::forUser($vendorA->user)->allows('update', $otherShipment));

        $ownShipment = Shipment::factory()->create([
            'vendor_order_id' => $this->vendorOrderFor($vendorA)->id,
        ]);

        $this->assertTrue(Gate::forUser($vendorA->user)->allows('view', $ownShipment));
        $this->assertTrue(Gate::forUser($vendorA->user)->allows('update', $ownShipment));
    }

    public function test_a_shipment_without_a_vendor_order_is_denied(): void
    {
        $vendor = $this->approvedVendor();
        $orphan = Shipment::factory()->create(['vendor_order_id' => null]);

        $this->assertFalse(Gate::forUser($vendor->user)->allows('view', $orphan));
    }

    public function test_returns_are_visible_to_the_customer_and_the_owning_vendor_only(): void
    {
        $vendorA = $this->approvedVendor();
        $vendorB = $this->approvedVendor();
        $customer = User::factory()->customer()->create();

        $orderItem = OrderItem::factory()->create(['vendor_id' => $vendorB->id]);
        $return = ProductReturn::factory()->create([
            'order_id' => $orderItem->order_id,
            'order_item_id' => $orderItem->id,
            'user_id' => $customer->id,
        ]);

        // The customer who opened the return can view it but not action it.
        $this->assertTrue(Gate::forUser($customer)->allows('view', $return));
        $this->assertFalse(Gate::forUser($customer)->allows('update', $return));

        // A different vendor cannot touch it; the fulfilling vendor can.
        $this->assertFalse(Gate::forUser($vendorA->user)->allows('update', $return));
        $this->assertTrue(Gate::forUser($vendorB->user)->allows('update', $return));
    }

    public function test_super_admins_bypass_every_policy(): void
    {
        $product = Product::factory()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('update', $product));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Product::class));
    }

    public function test_another_customer_cannot_touch_a_foreign_address(): void
    {
        [$alice, $bob] = User::factory()->customer()->count(2)->create();
        $address = Address::factory()->create(['user_id' => $bob->id]);

        $this->assertFalse(Gate::forUser($alice)->allows('view', $address));

        Sanctum::actingAs($alice);
        $this->getJson("/api/v1/user/addresses/{$address->id}")->assertStatus(403);

        Sanctum::actingAs($bob);
        $this->getJson("/api/v1/user/addresses/{$address->id}")->assertOk();
    }
}
