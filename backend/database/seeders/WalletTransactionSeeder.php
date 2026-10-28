<?php

namespace Database\Seeders;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WalletTransactionSeeder extends Seeder
{
    /** @var array<int, array{type: WalletTransactionType, dir: string, amount: float, desc: string, days: int}> */
    private const EVENTS = [
        ['type' => 'COMMISSION', 'dir' => 'in', 'amount' => 12000, 'desc' => 'Platform commission earned on orders', 'days' => 30],
        ['type' => 'RECHARGE', 'dir' => 'in', 'amount' => 5000, 'desc' => 'Wallet recharge via UPI', 'days' => 27],
        ['type' => 'REFUND', 'dir' => 'in', 'amount' => 800, 'desc' => 'Customer refund recovered from gateway', 'days' => 24],
        ['type' => 'ORDER_PAYOUT', 'dir' => 'in', 'amount' => 3200, 'desc' => 'Payout from marketplace settlement', 'days' => 21],
        ['type' => 'ORDER_PAYMENT', 'dir' => 'out', 'amount' => 6000, 'desc' => 'Settlement paid out to vendor', 'days' => 18],
        ['type' => 'WITHDRAWAL', 'dir' => 'out', 'amount' => 2000, 'desc' => 'Withdrawal to linked bank account', 'days' => 15],
        ['type' => 'COMMISSION', 'dir' => 'in', 'amount' => 3000, 'desc' => 'Weekly commission settlement', 'days' => 12],
        ['type' => 'ADJUSTMENT', 'dir' => 'out', 'amount' => 500, 'desc' => 'Fee correction for failed payment', 'days' => 9],
        ['type' => 'ORDER_PAYMENT', 'dir' => 'out', 'amount' => 1500, 'desc' => 'Refund processed to customer wallet', 'days' => 6],
        ['type' => 'CREDIT', 'dir' => 'in', 'amount' => 2000, 'desc' => 'Manual credit added by support', 'days' => 4],
        ['type' => 'RECHARGE', 'dir' => 'in', 'amount' => 700, 'desc' => 'Wallet recharge via Razorpay', 'days' => 2],
        ['type' => 'WITHDRAWAL', 'dir' => 'out', 'amount' => 8000, 'desc' => 'Withdrawal to Razorpay', 'days' => 1],
    ];

    public function run(WalletService $walletService): void
    {
        $admin = User::query()->where('email', 'admin@multivendor.test')->first();

        if (! $admin) {
            $this->command?->warn('Admin user not found; skipping wallet transaction seeding.');

            return;
        }

        $wallet = $walletService->walletFor($admin);

        if ($wallet->transactions()->count() > 3) {
            $this->command?->info('Wallet transactions already seeded. Skipping.');

            return;
        }

        $captured = [];

        foreach (self::EVENTS as $event) {
            try {
                if ($event['dir'] === 'in') {
                    $walletService->credit(
                        $admin,
                        $event['amount'],
                        $event['desc'],
                        WalletTransactionType::tryFrom($event['type']) ?? WalletTransactionType::Credit,
                    );
                } else {
                    $walletService->debit(
                        $admin,
                        $event['amount'],
                        $event['desc'],
                        WalletTransactionType::tryFrom($event['type']) ?? WalletTransactionType::Debit,
                    );
                }
            } catch (\Throwable $e) {
                $this->command?->warn('Skipped event ('.$event['type'].' '.$event['amount'].'): '.$e->getMessage());

                continue;
            }

            $captured[] = ['tx' => $wallet->transactions()->latest('id')->first(), 'days' => $event['days']];
        }

        // One pending order-payment hold so the PENDING state is visible.
        $before = (float) $wallet->totalBalance();
        $wallet->transactions()->create([
            'type' => WalletTransactionType::OrderPayment->value,
            'amount' => 1200,
            'balance_before' => $before,
            'balance_after' => $before,
            'status' => WalletTransactionStatus::Pending->value,
            'description' => 'Authorized payment hold awaiting confirmation',
        ]);

        // Backdate created_at so the history spreads over the last month.
        DB::transaction(function () use ($captured): void {
            foreach ($captured as $entry) {
                $entry['tx']?->update([
                    'created_at' => now()->subDays($entry['days'])->setTime(rand(9, 19), rand(0, 59)),
                    'updated_at' => now()->subDays($entry['days'])->setTime(rand(9, 19), rand(0, 59)),
                ]);
            }
        });

        $this->command?->info('Seeded '.count($captured).' wallet transactions across the platform wallet.');
    }
}
