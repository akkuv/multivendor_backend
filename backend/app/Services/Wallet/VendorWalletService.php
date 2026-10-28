<?php

namespace App\Services\Wallet;

use App\Enums\NotificationType;
use App\Enums\VendorWalletStatus;
use App\Enums\VendorWalletTransactionStatus;
use App\Enums\VendorWalletTransactionType;
use App\Models\VendorProfile;
use App\Models\VendorWallet;
use App\Models\VendorWalletTransaction;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VendorWalletService
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function walletFor(VendorProfile $vendor): VendorWallet
    {
        return $vendor->wallet()->firstOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'currency' => (string) config('commission.currency', 'INR'),
                'available_balance' => 0,
                'pending_balance' => 0,
                'held_balance' => 0,
                'paid_total' => 0,
                'status' => VendorWalletStatus::Active->value,
            ],
        );
    }

    /**
     * Add proceeds to the available (withdrawable) balance, e.g. when a
     * delivered order clears its return window.
     */
    public function creditAvailable(
        VendorProfile $vendor,
        float $amount,
        string $description,
        VendorWalletTransactionType $type,
        ?Model $reference = null,
    ): VendorWallet {
        if ($amount <= 0) {
            throw new RuntimeException(__('Credit amount must be greater than zero.'));
        }

        $wallet = DB::transaction(function () use ($vendor, $amount, $description, $type, $reference): VendorWallet {
            $wallet = $this->lockWallet($this->walletFor($vendor));
            $this->guardStatus($wallet);

            $before = $wallet->totalBalance();
            $wallet->forceFill([
                'available_balance' => (float) $wallet->available_balance + $amount,
            ])->save();

            $this->recordTransaction($wallet, $type, $amount, $before, $before + $amount, $description, $reference);

            return $wallet;
        });

        if ($vendor->user !== null) {
            $this->dispatcher->send(
                $vendor->user,
                NotificationType::VendorWalletCredited,
                __('Wallet credited'),
                __(':amount has been credited to your wallet.', ['amount' => number_format($amount, 2)]),
                ['amount' => (float) $amount, 'balance' => (float) $wallet->available_balance],
            );
        }

        return $wallet;
    }

    /**
     * Move funds that are already available into the held bucket while a
     * payout request is being processed.
     */
    public function moveToHeld(
        VendorProfile $vendor,
        float $amount,
        string $description,
        ?Model $reference = null,
    ): VendorWallet {
        if ($amount <= 0) {
            throw new RuntimeException(__('Hold amount must be greater than zero.'));
        }

        return DB::transaction(function () use ($vendor, $amount, $description, $reference): VendorWallet {
            $wallet = $this->lockWallet($this->walletFor($vendor));
            $this->guardStatus($wallet);

            if ((float) $wallet->available_balance < $amount) {
                throw new RuntimeException(__('Insufficient available balance.'));
            }

            $before = $wallet->totalBalance();

            $wallet->forceFill([
                'available_balance' => (float) $wallet->available_balance - $amount,
                'held_balance' => (float) $wallet->held_balance + $amount,
            ])->save();

            $this->recordTransaction($wallet, VendorWalletTransactionType::PayoutHold, $amount, $before, $before, $description, $reference);

            return $wallet;
        });
    }

    /**
     * Release a held amount back to available, e.g. when a payout is
     * rejected.
     */
    public function releaseHeld(
        VendorProfile $vendor,
        float $amount,
        string $description,
        ?Model $reference = null,
    ): VendorWallet {
        if ($amount <= 0) {
            throw new RuntimeException(__('Release amount must be greater than zero.'));
        }

        return DB::transaction(function () use ($vendor, $amount, $description, $reference): VendorWallet {
            $wallet = $this->lockWallet($this->walletFor($vendor));
            $this->guardStatus($wallet);

            if ((float) $wallet->held_balance < $amount) {
                throw new RuntimeException(__('Insufficient held balance.'));
            }

            $before = $wallet->totalBalance();

            $wallet->forceFill([
                'held_balance' => (float) $wallet->held_balance - $amount,
                'available_balance' => (float) $wallet->available_balance + $amount,
            ])->save();

            $this->recordTransaction($wallet, VendorWalletTransactionType::PayoutRelease, $amount, $before, $before, $description, $reference);

            return $wallet;
        });
    }

    /**
     * Mark a held payout as paid: remove it from held and accrue it to the
     * lifetime paid total.
     */
    public function settlePaid(
        VendorProfile $vendor,
        float $amount,
        string $description,
        ?Model $reference = null,
    ): VendorWallet {
        if ($amount <= 0) {
            throw new RuntimeException(__('Paid amount must be greater than zero.'));
        }

        return DB::transaction(function () use ($vendor, $amount, $description, $reference): VendorWallet {
            $wallet = $this->lockWallet($this->walletFor($vendor));
            $this->guardStatus($wallet);

            if ((float) $wallet->held_balance < $amount) {
                throw new RuntimeException(__('Insufficient held balance to settle as paid.'));
            }

            $before = $wallet->totalBalance();

            $wallet->forceFill([
                'held_balance' => (float) $wallet->held_balance - $amount,
                'paid_total' => (float) $wallet->paid_total + $amount,
            ])->save();

            $this->recordTransaction($wallet, VendorWalletTransactionType::PayoutPaid, $amount, $before, $before - $amount, $description, $reference);

            return $wallet;
        });
    }

    /**
     * Reverse a previously credited amount (e.g. a refunded order that was
     * already settled). Debits available first, then held, then pending.
     */
    public function debit(
        VendorProfile $vendor,
        float $amount,
        string $description,
        VendorWalletTransactionType $type,
        ?Model $reference = null,
    ): VendorWallet {
        if ($amount <= 0) {
            throw new RuntimeException(__('Debit amount must be greater than zero.'));
        }

        return DB::transaction(function () use ($vendor, $amount, $description, $type, $reference): VendorWallet {
            $wallet = $this->lockWallet($this->walletFor($vendor));
            $this->guardStatus($wallet);

            $before = $wallet->totalBalance();
            $remaining = $amount;

            $available = (float) $wallet->available_balance;
            $debitAvailable = min($available, $remaining);
            $remaining -= $debitAvailable;

            $held = (float) $wallet->held_balance;
            $debitHeld = min($held, $remaining);
            $remaining -= $debitHeld;

            $pending = (float) $wallet->pending_balance;
            $debitPending = min($pending, $remaining);
            $remaining -= $debitPending;

            if ($remaining > 0.009) {
                throw new RuntimeException(__('Insufficient wallet balance to reverse payment.'));
            }

            $wallet->forceFill([
                'available_balance' => round((float) $wallet->available_balance - $debitAvailable, 2),
                'held_balance' => round((float) $wallet->held_balance - $debitHeld, 2),
                'pending_balance' => round((float) $wallet->pending_balance - $debitPending, 2),
            ])->save();

            $this->recordTransaction($wallet, $type, $amount, $before, $before - $amount, $description, $reference);

            return $wallet;
        });
    }

    /** @return array<string, mixed> */
    public function dashboard(VendorProfile $vendor, int $perPage = 15): array
    {
        $wallet = $this->walletFor($vendor);

        $transactions = $wallet->transactions()
            ->with('reference')
            ->orderByDesc('id')
            ->paginate($perPage);

        return [
            'wallet' => $wallet,
            'transactions' => $transactions,
        ];
    }

    protected function recordTransaction(
        VendorWallet $wallet,
        VendorWalletTransactionType $type,
        float $amount,
        float $balanceBefore,
        float $balanceAfter,
        string $description,
        ?Model $reference,
        VendorWalletTransactionStatus $status = VendorWalletTransactionStatus::Completed,
    ): VendorWalletTransaction {
        return $wallet->transactions()->create([
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'type' => $type->value,
            'status' => $status->value,
            'description' => $description,
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id' => $reference?->getKey(),
        ]);
    }

    protected function lockWallet(VendorWallet $wallet): VendorWallet
    {
        /** @var VendorWallet $locked */
        $locked = VendorWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

        return $locked;
    }

    /**
     * Lock the vendor's wallet row for the duration of the surrounding
     * transaction, so a balance check cannot race with concurrent debits.
     */
    public function lockWalletFor(VendorProfile $vendor): VendorWallet
    {
        return $this->lockWallet($this->walletFor($vendor));
    }

    protected function guardStatus(VendorWallet $wallet): void
    {
        if (! $wallet->status->canTransact()) {
            throw new RuntimeException(__('Vendor wallet is not active.'));
        }
    }
}
