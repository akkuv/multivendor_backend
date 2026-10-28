<?php

namespace Tests\Feature;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminWalletTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin, ['admin']);

        return $admin;
    }

    public function test_admin_can_fetch_wallet_dashboard(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/wallet')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'currency', 'available_balance', 'pending_balance', 'total_balance', 'status'],
            ]);
    }

    public function test_admin_can_filter_transactions_by_type(): void
    {
        $admin = $this->actingAsAdmin();
        $wallet = $admin->wallet()->firstOrCreate(['user_id' => $admin->id], ['currency' => 'INR']);

        WalletTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => WalletTransactionType::Recharge->value,
            'description' => 'Razorpay recharge done',
        ]);
        WalletTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => WalletTransactionType::Debit->value,
            'description' => 'Order payment deduction',
        ]);

        $this->getJson('/api/v1/admin/wallet/transactions?type='.WalletTransactionType::Recharge->value)
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.type', WalletTransactionType::Recharge->value);
    }

    public function test_admin_can_search_transactions_by_description(): void
    {
        $admin = $this->actingAsAdmin();
        $wallet = $admin->wallet()->firstOrCreate(['user_id' => $admin->id], ['currency' => 'INR']);

        WalletTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => WalletTransactionType::Credit->value,
            'description' => 'Vendor commission payout',
        ]);
        WalletTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => WalletTransactionType::Refund->value,
            'description' => 'Customer refund issued',
        ]);

        $this->getJson('/api/v1/admin/wallet/transactions?q=commission')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.description', 'Vendor commission payout');
    }

    public function test_admin_transactions_include_status_and_balance(): void
    {
        $admin = $this->actingAsAdmin();
        $wallet = $admin->wallet()->firstOrCreate(['user_id' => $admin->id], ['currency' => 'INR']);

        WalletTransaction::factory()->create([
            'wallet_id' => $wallet->id,
            'status' => WalletTransactionStatus::Pending->value,
        ]);

        $this->getJson('/api/v1/admin/wallet/transactions')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        ['id', 'type', 'amount', 'status', 'description', 'created_at'],
                    ],
                ],
            ])
            ->assertJsonPath('data.data.0.status', WalletTransactionStatus::Pending->value);
    }
}
