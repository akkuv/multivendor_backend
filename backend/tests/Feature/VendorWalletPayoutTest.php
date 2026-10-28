<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\VendorStatus;
use App\Enums\VendorWalletTransactionType;
use App\Models\User;
use App\Models\VendorPayout;
use App\Models\VendorProfile;
use App\Notifications\AppNotification;
use App\Services\Wallet\VendorPayoutService;
use App\Services\Wallet\VendorWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorWalletPayoutTest extends TestCase
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

    private function actingAsVendor(VendorProfile $vendor): void
    {
        Sanctum::actingAs($vendor->user);
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

    public function test_settlement_credit_notifies_the_vendor(): void
    {
        $vendor = $this->approvedVendor();

        $this->credit($vendor, 500);

        $this->assertDatabaseHas('notifications', [
            'type' => AppNotification::class,
            'notifiable_id' => $vendor->user->id,
            'data->type' => NotificationType::VendorWalletCredited->value,
        ]);
    }

    public function test_vendor_wallet_dashboard_returns_balances_and_ledger(): void
    {
        $vendor = $this->approvedVendor();
        $this->credit($vendor, 500);
        $this->actingAsVendor($vendor);

        $this->getJson('/api/v1/vendor/wallet')
            ->assertOk()
            ->assertJsonPath('data.wallet.available_balance', '500.00')
            ->assertJsonPath('data.wallet.held_balance', '0.00')
            ->assertJsonPath('data.wallet.total_balance', '500.00')
            ->assertJsonPath('data.pending_settlement', '0.00')
            ->assertJsonCount(1, 'data.transactions.data');
    }

    public function test_vendor_can_request_payout_which_holds_funds(): void
    {
        $vendor = $this->approvedVendor();
        $this->credit($vendor, 1000);
        $this->actingAsVendor($vendor);

        $this->postJson('/api/v1/vendor/payouts', [
            'amount' => 400,
            'method' => 'bank_transfer',
            'account_details' => ['account_number' => '1234', 'ifsc' => 'TEST0001'],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'PENDING');

        $payout = VendorPayout::where('vendor_id', $vendor->id)->firstOrFail();
        $this->assertSame('400.00', $payout->amount);

        $wallet = $vendor->wallet->refresh();
        $this->assertSame('600.00', $wallet->available_balance);
        $this->assertSame('400.00', $wallet->held_balance);

        $this->assertDatabaseHas('vendor_wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => VendorWalletTransactionType::PayoutHold->value,
            'amount' => 400.00,
            'balance_before' => 1000.00,
            'balance_after' => 1000.00,
            'reference_type' => VendorPayout::class,
            'reference_id' => $payout->id,
        ]);
    }

    public function test_vendor_cannot_request_more_than_available_balance(): void
    {
        $vendor = $this->approvedVendor();
        $this->credit($vendor, 200);
        $this->actingAsVendor($vendor);

        $this->postJson('/api/v1/vendor/payouts', ['amount' => 300])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_vendor_cannot_view_another_vendors_payout(): void
    {
        $vendorA = $this->approvedVendor();
        $this->credit($vendorA, 500);
        app(VendorPayoutService::class)->request($vendorA, 100);

        $other = $this->approvedVendor();
        $this->actingAsVendor($other);

        $this->getJson('/api/v1/vendor/payouts/'.$vendorA->payouts()->first()->id)
            ->assertStatus(404);
    }

    public function test_unapproved_vendor_is_rejected(): void
    {
        $user = User::factory()->vendor()->create();
        VendorProfile::factory()->create([
            'user_id' => $user->id,
            'status' => VendorStatus::Pending->value,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/vendor/wallet')->assertStatus(403);
    }
}
