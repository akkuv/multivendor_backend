<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Enums\VendorCommissionStatus;
use App\Enums\VendorOrderStatus;
use App\Enums\VendorStatus;
use App\Enums\VendorWalletTransactionType;
use App\Events\RefundCompleted;
use App\Listeners\HandleRefundCompleted;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductReturn;
use App\Models\Refund;
use App\Models\User;
use App\Models\VendorCommission;
use App\Models\VendorOrder;
use App\Models\VendorProfile;
use App\Services\Order\VendorSettlementService;
use App\Services\Wallet\VendorPayoutService;
use App\Services\Wallet\VendorWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSettlementTest extends TestCase
{
    use RefreshDatabase;

    private function approvedVendor(): VendorProfile
    {
        $vendor = VendorProfile::factory()->create([
            'status' => VendorStatus::Approved->value,
            'is_approved' => true,
        ]);

        $vendor->user->refresh();

        return $vendor;
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
    }

    private function deliveredVendorOrder(VendorProfile $vendor, array $overrides = []): VendorOrder
    {
        $order = Order::factory()->create([
            'order_status' => OrderStatus::Delivered->value,
            'payment_status' => PaymentStatus::Paid->value,
        ]);

        $vendorOrder = VendorOrder::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Delivered->value,
            'delivered_at' => now()->subDays(10),
            ...$overrides,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'vendor_order_id' => $vendorOrder->id,
            'unit_price' => 1000,
            'quantity' => 2,
            'total' => 2000,
            'commission_rate' => 10,
            'commission_amount' => 200,
            'status' => OrderStatus::Delivered->value,
        ]);

        return $vendorOrder;
    }

    private function credit(VendorProfile $vendor, float $amount): void
    {
        app(VendorWalletService::class)->creditAvailable(
            $vendor,
            $amount,
            'Test settlement.',
            VendorWalletTransactionType::Commission,
        );
    }

    private function settle(VendorOrder $vendorOrder): VendorCommission
    {
        return app(VendorSettlementService::class)->settle($vendorOrder);
    }

    private function completedRefundFor(VendorOrder $vendorOrder, OrderItem $item): Refund
    {
        $refund = Refund::factory()->completed()->create([
            'order_id' => $vendorOrder->order_id,
            'order_item_id' => $item->id,
            'user_id' => $vendorOrder->order->user_id,
        ]);

        Notification::fake();
        app(HandleRefundCompleted::class)->handle(new RefundCompleted($refund));

        return $refund->refresh();
    }

    private function deliveredVendorOrderWithItems(VendorProfile $vendor, array $items): VendorOrder
    {
        $order = Order::factory()->create([
            'order_status' => OrderStatus::Delivered->value,
            'payment_status' => PaymentStatus::Paid->value,
        ]);

        $vendorOrder = VendorOrder::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Delivered->value,
            'delivered_at' => now()->subDays(10),
        ]);

        foreach ($items as $item) {
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'vendor_id' => $vendor->id,
                'vendor_order_id' => $vendorOrder->id,
                'status' => OrderStatus::Delivered->value,
                ...$item,
            ]);
        }

        return $vendorOrder;
    }

    public function test_admin_can_trigger_settlement_and_credit_vendor_wallet(): void
    {
        $vendor = $this->approvedVendor();
        $this->deliveredVendorOrder($vendor, ['delivered_at' => now()->subDays(2)]);

        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/settlements/run')
            ->assertOk()
            ->assertJsonPath('data.settled', 0);

        $eligibleOrder = $this->deliveredVendorOrder($vendor);

        $this->postJson('/api/v1/admin/settlements/run')
            ->assertOk()
            ->assertJsonPath('data.settled', 1);

        $this->assertDatabaseHas('vendor_commissions', [
            'vendor_id' => $vendor->id,
            'vendor_order_id' => $eligibleOrder->id,
            'eligible_amount' => 2000.00,
            'commission_amount' => 200.00,
            'net_payable' => 1800.00,
            'status' => VendorCommissionStatus::Settled->value,
        ]);

        $this->assertDatabaseHas('commission_transactions', [
            'vendor_id' => $vendor->id,
            'amount' => 200.00,
            'type' => 'CHARGE',
        ]);

        $wallet = $vendor->wallet->refresh();
        $this->assertSame('1800.00', $wallet->available_balance);

        $this->assertDatabaseHas('vendor_wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => VendorWalletTransactionType::Commission->value,
            'amount' => 1800.00,
            'balance_before' => 0.00,
            'balance_after' => 1800.00,
        ]);

        $this->postJson('/api/v1/admin/settlements/run')
            ->assertOk()
            ->assertJsonPath('data.settled', 0);
    }

    public function test_order_with_open_return_is_not_settled(): void
    {
        $vendor = $this->approvedVendor();
        $vendorOrder = $this->deliveredVendorOrder($vendor);

        ProductReturn::factory()->create([
            'order_id' => $vendorOrder->order_id,
            'order_item_id' => $vendorOrder->items()->first()->id,
            'user_id' => $vendorOrder->order->user_id,
            'status' => ReturnStatus::Requested->value,
        ]);

        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/settlements/run')
            ->assertOk()
            ->assertJsonPath('data.settled', 0);

        $this->assertDatabaseMissing('vendor_commissions', [
            'vendor_order_id' => $vendorOrder->id,
        ]);
    }

    public function test_admin_can_approve_and_mark_payout_paid(): void
    {
        $vendor = $this->approvedVendor();
        $this->credit($vendor, 1000);
        $payout = app(VendorPayoutService::class)->request($vendor, 400);
        $wallet = $vendor->wallet->refresh();

        $this->actingAsAdmin();

        $this->putJson('/api/v1/admin/payouts/'.$payout->id.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');

        $this->putJson('/api/v1/admin/payouts/'.$payout->id.'/mark-paid')
            ->assertOk()
            ->assertJsonPath('data.status', 'PAID');

        $wallet = $wallet->refresh();
        $this->assertSame('600.00', $wallet->available_balance);
        $this->assertSame('0.00', $wallet->held_balance);
        $this->assertSame('400.00', $wallet->paid_total);

        $this->assertDatabaseHas('vendor_wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => VendorWalletTransactionType::PayoutPaid->value,
            'balance_before' => 1000.00,
            'balance_after' => 600.00,
        ]);
    }

    public function test_admin_can_reject_payout_and_release_hold(): void
    {
        $vendor = $this->approvedVendor();
        $this->credit($vendor, 1000);
        $payout = app(VendorPayoutService::class)->request($vendor, 400);

        $this->actingAsAdmin();

        $this->putJson('/api/v1/admin/payouts/'.$payout->id.'/reject', ['note' => 'Invalid account'])
            ->assertOk()
            ->assertJsonPath('data.status', 'REJECTED');

        $wallet = $vendor->wallet->refresh();
        $this->assertSame('1000.00', $wallet->available_balance);
        $this->assertSame('0.00', $wallet->held_balance);

        $this->assertDatabaseHas('vendor_wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => VendorWalletTransactionType::PayoutRelease->value,
            'balance_before' => 1000.00,
            'balance_after' => 1000.00,
        ]);
    }

    public function test_payout_cannot_be_actioned_after_specific_states(): void
    {
        $vendor = $this->approvedVendor();
        $this->credit($vendor, 1000);
        $payout = app(VendorPayoutService::class)->request($vendor, 400);

        $this->actingAsAdmin();

        $this->putJson('/api/v1/admin/payouts/'.$payout->id.'/mark-paid')
            ->assertOk()
            ->assertJsonPath('data.status', 'PAID');

        $this->putJson('/api/v1/admin/payouts/'.$payout->id.'/approve')
            ->assertStatus(422);

        $this->putJson('/api/v1/admin/payouts/'.$payout->id.'/reject')
            ->assertStatus(422);
    }

    public function test_only_super_admin_can_manage_settlements(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->postJson('/api/v1/admin/settlements/run')->assertStatus(403);
        $this->getJson('/api/v1/admin/payouts')->assertStatus(403);
    }

    public function test_full_refund_of_settled_order_reverses_commission_and_debits_wallet(): void
    {
        $vendor = $this->approvedVendor();
        $vendorOrder = $this->deliveredVendorOrder($vendor);
        $item = $vendorOrder->items()->first();
        $this->settle($vendorOrder);

        $this->assertDatabaseHas('vendor_commissions', [
            'vendor_order_id' => $vendorOrder->id,
            'status' => VendorCommissionStatus::Settled->value,
        ]);
        $this->assertSame('1800.00', $vendor->wallet->refresh()->available_balance);

        $this->completedRefundFor($vendorOrder, $item);

        $this->assertDatabaseHas('vendor_commissions', [
            'vendor_order_id' => $vendorOrder->id,
            'status' => VendorCommissionStatus::Reversed->value,
            'reversal_reason' => "Full refund for order {$vendorOrder->vendor_order_number}.",
        ]);
        $this->assertDatabaseHas('commission_transactions', [
            'vendor_commission_id' => VendorCommission::where('vendor_order_id', $vendorOrder->id)->value('id'),
            'amount' => 200.00,
            'type' => 'REVERSAL',
        ]);

        $this->assertSame('0.00', $vendor->wallet->refresh()->available_balance);

        $this->assertDatabaseHas('vendor_wallet_transactions', [
            'wallet_id' => $vendor->wallet->id,
            'type' => VendorWalletTransactionType::CommissionReversal->value,
            'amount' => 1800.00,
            'balance_before' => 1800.00,
            'balance_after' => 0.00,
        ]);
    }

    public function test_refund_of_unsettled_order_does_not_touch_vendor_wallet(): void
    {
        $vendor = $this->approvedVendor();
        $vendorOrder = $this->deliveredVendorOrder($vendor);
        $item = $vendorOrder->items()->first();

        $this->completedRefundFor($vendorOrder, $item);

        $this->assertDatabaseMissing('vendor_commissions', [
            'vendor_order_id' => $vendorOrder->id,
        ]);
        $this->assertDatabaseMissing('vendor_wallet_transactions', [
            'type' => VendorWalletTransactionType::CommissionReversal->value,
        ]);
    }

    public function test_partial_refund_of_settled_order_debits_the_refunded_line(): void
    {
        $vendor = $this->approvedVendor();

        $vendorOrder = $this->deliveredVendorOrderWithItems($vendor, [
            ['total' => 2000, 'commission_rate' => 10, 'commission_amount' => 200],
            ['total' => 3000, 'commission_rate' => 10, 'commission_amount' => 300],
        ]);

        $commission = $this->settle($vendorOrder);
        [$itemA, $itemB] = $vendorOrder->items()->orderBy('id')->get();

        $this->assertSame('4500.00', $vendor->wallet->refresh()->available_balance);

        $this->completedRefundFor($vendorOrder, $itemA);

        $commission->refresh();
        $this->assertSame(VendorCommissionStatus::Settled, $commission->status);
        $this->assertSame('2700.00', $commission->net_payable);
        $this->assertSame('300.00', $commission->commission_amount);
        $this->assertSame('2700.00', $vendor->wallet->refresh()->available_balance);

        $this->assertDatabaseHas('commission_transactions', [
            'vendor_commission_id' => $commission->id,
            'amount' => 1800.00,
            'type' => 'REVERSAL',
        ]);
        $this->assertDatabaseHas('vendor_wallet_transactions', [
            'wallet_id' => $vendor->wallet->id,
            'type' => VendorWalletTransactionType::CommissionReversal->value,
            'amount' => 1800.00,
            'balance_before' => 4500.00,
            'balance_after' => 2700.00,
        ]);
    }

    public function test_refund_of_all_items_reverses_settled_commission(): void
    {
        $vendor = $this->approvedVendor();

        $vendorOrder = $this->deliveredVendorOrderWithItems($vendor, [
            ['total' => 2000, 'commission_rate' => 10, 'commission_amount' => 200],
            ['total' => 3000, 'commission_rate' => 10, 'commission_amount' => 300],
        ]);

        $commission = $this->settle($vendorOrder);
        [$itemA, $itemB] = $vendorOrder->items()->orderBy('id')->get();

        $this->completedRefundFor($vendorOrder, $itemA);
        $this->completedRefundFor($vendorOrder, $itemB);

        $commission->refresh();
        $this->assertSame(VendorCommissionStatus::Reversed, $commission->status);
        $this->assertSame('0.00', $vendor->wallet->refresh()->available_balance);
    }

    public function test_console_command_settles_eligible_orders_daily(): void
    {
        $vendor = $this->approvedVendor();
        $eligible = $this->deliveredVendorOrder($vendor);
        $this->deliveredVendorOrder($vendor, ['delivered_at' => now()->subDays(2)]);

        $this->artisan('commission:settle')->assertSuccessful();

        $this->assertDatabaseHas('vendor_commissions', [
            'vendor_order_id' => $eligible->id,
            'status' => VendorCommissionStatus::Settled->value,
        ]);
        $this->assertSame('1800.00', $vendor->wallet->refresh()->available_balance);

        $this->artisan('commission:settle')->assertSuccessful();
        $this->assertSame(1, VendorCommission::count());
    }

    public function test_console_command_as_of_option_extends_eligibility_window(): void
    {
        $vendor = $this->approvedVendor();
        $recent = $this->deliveredVendorOrder($vendor, ['delivered_at' => now()->subDays(2)]);

        $this->artisan('commission:settle')->assertSuccessful();
        $this->assertDatabaseMissing('vendor_commissions', [
            'vendor_order_id' => $recent->id,
        ]);

        $this->artisan('commission:settle', [
            '--as-of' => now()->addDays(10)->toISOString(),
        ])->assertSuccessful();

        $this->assertDatabaseHas('vendor_commissions', [
            'vendor_order_id' => $recent->id,
            'status' => VendorCommissionStatus::Settled->value,
        ]);
    }
}
