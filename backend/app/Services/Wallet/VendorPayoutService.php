<?php

namespace App\Services\Wallet;

use App\Enums\VendorPayoutStatus;
use App\Models\VendorPayout;
use App\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VendorPayoutService
{
    public function __construct(protected VendorWalletService $walletService) {}

    /**
     * @param  array<string, mixed>  $accountDetails
     */
    public function request(
        VendorProfile $vendor,
        float $amount,
        string $method = 'bank_transfer',
        ?array $accountDetails = null,
        ?string $note = null,
    ): VendorPayout {
        $amount = round($amount, 2);

        $this->assertRequestValid($amount, $method);

        return DB::transaction(function () use ($vendor, $amount, $method, $accountDetails, $note): VendorPayout {
            $wallet = $this->walletService->lockWalletFor($vendor);

            if ((float) $wallet->available_balance < $amount) {
                throw new RuntimeException(__('Available balance is less than the requested payout amount.'));
            }

            $payout = $vendor->payouts()->create([
                'amount' => $amount,
                'method' => $method,
                'account_details' => $accountDetails,
                'status' => VendorPayoutStatus::Pending->value,
                'request_note' => $note,
                'requested_at' => now(),
            ]);

            $this->walletService->moveToHeld(
                $vendor,
                $amount,
                "Payout request #{$payout->id}.",
                $payout,
            );

            return $payout;
        });
    }

    public function approve(VendorPayout $payout, ?string $note = null): VendorPayout
    {
        return $this->transition($payout, VendorPayoutStatus::Approved, 'approved_at', $note);
    }

    public function markPaid(VendorPayout $payout, ?string $note = null): VendorPayout
    {
        $payout = $this->transition($payout, VendorPayoutStatus::Paid, 'paid_at', $note);

        $this->walletService->settlePaid(
            $payout->vendor,
            (float) $payout->amount,
            "Payout #{$payout->id} paid.",
            $payout,
        );

        return $payout->fresh();
    }

    public function reject(VendorPayout $payout, ?string $note = null): VendorPayout
    {
        $payout = $this->transition($payout, VendorPayoutStatus::Rejected, 'rejected_at', $note);

        $this->walletService->releaseHeld(
            $payout->vendor,
            (float) $payout->amount,
            "Payout #{$payout->id} rejected.",
            $payout,
        );

        return $payout->fresh();
    }

    protected function transition(
        VendorPayout $payout,
        VendorPayoutStatus $to,
        string $timestampColumn,
        ?string $note,
    ): VendorPayout {
        $current = $payout->status;

        if (! $current->canTransitionTo($to)) {
            throw new RuntimeException("Payout cannot transition from {$current->value} to {$to->value}.");
        }

        $payout->forceFill([
            'status' => $to->value,
            $timestampColumn => now(),
            'admin_note' => $note ?? $payout->admin_note,
        ])->save();

        return $payout->fresh();
    }

    protected function assertRequestValid(float $amount, string $method): void
    {
        $min = (float) config('commission.min_payout_amount', 100);
        $max = config('commission.max_payout_amount');

        if ($amount <= 0) {
            throw new RuntimeException(__('Payout amount must be greater than zero.'));
        }

        if ($amount < $min) {
            throw new RuntimeException("Minimum payout amount is {$min}.");
        }

        if ($max !== null && $amount > (float) $max) {
            throw new RuntimeException("Maximum payout amount is {$max}.");
        }

        $methods = (array) config('commission.payout_methods', ['bank_transfer', 'upi']);

        if (! in_array($method, $methods, true)) {
            throw new RuntimeException(__('Invalid payout method.'));
        }
    }
}
