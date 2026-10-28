<?php

namespace App\Services\Wallet;

use App\Enums\NotificationType;
use App\Enums\WalletHoldStatus;
use App\Enums\WalletStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletHold;
use App\Models\WalletTransaction;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    protected const CURRENCY = 'INR';

    public function __construct(protected NotificationDispatcher $dispatcher) {}

    public function walletFor(User $user): Wallet
    {
        return $user->wallet()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'currency' => self::CURRENCY,
                'available_balance' => 0,
                'pending_balance' => 0,
                'status' => WalletStatus::Active->value,
            ],
        );
    }

    public function balance(User $user): float
    {
        return (float) $this->walletFor($user)->available_balance;
    }

    public function credit(
        User $user,
        float $amount,
        string $description = '',
        WalletTransactionType $type = WalletTransactionType::Credit,
        ?Model $reference = null,
    ): Wallet {
        if ($amount <= 0) {
            throw new RuntimeException(__('Credit amount must be greater than zero.'));
        }

        return DB::transaction(function () use ($user, $amount, $description, $type, $reference): Wallet {
            if ($reference && $this->alreadyProcessed($reference, $type)) {
                return $this->walletFor($user);
            }

            $wallet = $this->lockWallet($this->walletFor($user));
            $this->guardStatus($wallet);

            $before = $wallet->totalBalance();
            $after = $before + $amount;

            $wallet->forceFill([
                'available_balance' => (float) $wallet->available_balance + $amount,
            ])->save();

            $this->recordTransaction($wallet, $type, $amount, $before, $after, $description, $reference, WalletTransactionStatus::Completed);

            $this->dispatcher->send(
                $user,
                $type === WalletTransactionType::Debit ? NotificationType::WalletDebited : NotificationType::WalletCredited,
                $type === WalletTransactionType::Debit ? __('Wallet debited') : __('Wallet credited'),
                $type === WalletTransactionType::Debit
                    ? __(':amount :currency was debited from your wallet.', ['amount' => number_format($amount, 2), 'currency' => self::CURRENCY])
                    : __(':amount :currency was credited to your wallet.', ['amount' => number_format($amount, 2), 'currency' => self::CURRENCY]),
                ['amount' => $amount],
                false,
            );

            return $wallet;
        });
    }

    public function debit(
        User $user,
        float $amount,
        string $description = '',
        WalletTransactionType $type = WalletTransactionType::Debit,
        ?Model $reference = null,
    ): Wallet {
        if ($amount <= 0) {
            throw new RuntimeException(__('Debit amount must be greater than zero.'));
        }

        return DB::transaction(function () use ($user, $amount, $description, $type, $reference): Wallet {
            if ($reference && $this->alreadyProcessed($reference, $type)) {
                return $this->walletFor($user);
            }

            $wallet = $this->lockWallet($this->walletFor($user));
            $this->guardStatus($wallet);
            $this->assertSufficientAvailable($wallet, $amount);

            $before = $wallet->totalBalance();
            $after = $before - $amount;

            $wallet->forceFill([
                'available_balance' => (float) $wallet->available_balance - $amount,
            ])->save();

            $this->recordTransaction($wallet, $type, $amount, $before, $after, $description, $reference, WalletTransactionStatus::Completed);

            return $wallet;
        });
    }

    public function recharge(User $user, float $amount, string $description = '', ?Model $reference = null): Wallet
    {
        return $this->credit($user, $amount, $description, WalletTransactionType::Recharge, $reference);
    }

    public function refund(User $user, float $amount, string $description, Model $reference): Wallet
    {
        return $this->credit($user, $amount, $description, WalletTransactionType::Refund, $reference);
    }

    public function adjust(User $user, float $amount, string $description, ?Model $reference = null): Wallet
    {
        if ($amount > 0) {
            return $this->credit($user, $amount, $description, WalletTransactionType::Adjustment, $reference);
        }

        return $this->debit($user, abs($amount), $description, WalletTransactionType::Adjustment, $reference);
    }

    /**
     * Move funds from available into a pending hold (escrow) before payment completes.
     */
    public function hold(
        User $user,
        float $amount,
        Model $reference,
        string $description,
        ?Carbon $expiresAt = null,
    ): WalletHold {
        if ($amount <= 0) {
            throw new RuntimeException(__('Hold amount must be greater than zero.'));
        }

        return DB::transaction(function () use ($user, $amount, $reference, $description, $expiresAt): WalletHold {
            $wallet = $this->lockWallet($this->walletFor($user));
            $this->guardStatus($wallet);
            $this->assertSufficientAvailable($wallet, $amount);

            $before = $wallet->totalBalance();
            $after = $before; // holds move available -> pending; total unchanged

            $wallet->forceFill([
                'available_balance' => (float) $wallet->available_balance - $amount,
                'pending_balance' => (float) $wallet->pending_balance + $amount,
            ])->save();

            $hold = $wallet->holds()->create([
                'user_id' => $user->id,
                'amount' => $amount,
                'status' => WalletHoldStatus::Pending->value,
                'reference_type' => $reference->getMorphClass(),
                'reference_id' => $reference->getKey(),
                'description' => $description,
                'expires_at' => $expiresAt,
            ]);

            $this->recordTransaction($wallet, WalletTransactionType::OrderPayment, $amount, $before, $after, $description, $reference, WalletTransactionStatus::Pending);

            return $hold;
        });
    }

    /**
     * Finalize a hold once payment is confirmed: funds leave the wallet.
     */
    public function captureHold(WalletHold $hold): void
    {
        DB::transaction(function () use ($hold): void {
            $hold = $this->lockHold($hold);
            $wallet = $this->lockWallet($hold->wallet);

            if ($hold->status !== WalletHoldStatus::Pending) {
                throw new RuntimeException(__('Hold is not in a pending state.'));
            }

            $before = $wallet->totalBalance();
            $after = $before - (float) $hold->amount;

            $wallet->forceFill([
                'pending_balance' => max(0.0, (float) $wallet->pending_balance - (float) $hold->amount),
            ])->save();

            $hold->forceFill([
                'status' => WalletHoldStatus::Captured->value,
                'captured_at' => now(),
            ])->save();

            $transaction = $this->pendingHoldTransaction($wallet, $hold);
            if ($transaction) {
                $transaction->forceFill([
                    'balance_after' => $after,
                    'status' => WalletTransactionStatus::Completed->value,
                ])->save();
            }
        });
    }

    /**
     * Cancel a hold without payment: funds return to the available balance.
     */
    public function releaseHold(WalletHold $hold, ?string $reason = null): void
    {
        DB::transaction(function () use ($hold, $reason): void {
            $hold = $this->lockHold($hold);
            $wallet = $this->lockWallet($hold->wallet);

            if ($hold->status !== WalletHoldStatus::Pending) {
                throw new RuntimeException(__('Hold is not in a pending state.'));
            }

            $wallet->forceFill([
                'pending_balance' => max(0.0, (float) $wallet->pending_balance - (float) $hold->amount),
                'available_balance' => (float) $wallet->available_balance + (float) $hold->amount,
            ])->save();

            $hold->forceFill([
                'status' => WalletHoldStatus::Released->value,
                'released_at' => now(),
            ])->save();

            $transaction = $this->pendingHoldTransaction($wallet, $hold);
            if ($transaction) {
                $transaction->forceFill([
                    'status' => WalletTransactionStatus::Failed->value,
                    'description' => $reason
                        ? trim(($transaction->description ?? '')." - {$reason}")
                        : $transaction->description,
                ])->save();
            }
        });
    }

    protected function guardStatus(Wallet $wallet): void
    {
        if (! $wallet->status->canTransact()) {
            throw new RuntimeException(__('Wallet is not active.'));
        }
    }

    protected function assertSufficientAvailable(Wallet $wallet, float $amount): void
    {
        if ((float) $wallet->available_balance < $amount) {
            throw new RuntimeException(__('Insufficient wallet balance.'));
        }
    }

    protected function lockWallet(Wallet $wallet): Wallet
    {
        /** @var Wallet $locked */
        $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

        return $locked;
    }

    protected function lockHold(WalletHold $hold): WalletHold
    {
        /** @var WalletHold $locked */
        $locked = WalletHold::whereKey($hold->id)->lockForUpdate()->firstOrFail();

        return $locked;
    }

    protected function pendingHoldTransaction(Wallet $wallet, WalletHold $hold): ?WalletTransaction
    {
        return $wallet->transactions()
            ->where('status', WalletTransactionStatus::Pending->value)
            ->where('reference_type', $hold->reference_type)
            ->where('reference_id', $hold->reference_id)
            ->latest('id')
            ->first();
    }

    /**
     * Idempotency guard: returns true when a completed wallet transaction of
     * the same type already exists for the given reference. Prevents credits
     * and debits from being applied twice for the same underlying event (e.g.
     * a retried gateway webhook).
     */
    protected function alreadyProcessed(Model $reference, WalletTransactionType $type): bool
    {
        return WalletTransaction::query()
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->where('type', $type->value)
            ->where('status', WalletTransactionStatus::Completed->value)
            ->exists();
    }

    protected function recordTransaction(
        Wallet $wallet,
        WalletTransactionType $type,
        float $amount,
        float $balanceBefore,
        float $balanceAfter,
        string $description,
        ?Model $reference,
        WalletTransactionStatus $status = WalletTransactionStatus::Completed,
    ): void {
        $wallet->transactions()->create([
            'type' => $type->value,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'status' => $status->value,
            'description' => $description,
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id' => $reference?->getKey(),
        ]);
    }
}
