<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Enums\WalletTransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddWalletMoneyRequest;
use App\Http\Requests\VerifyRechargePaymentRequest;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Payment\PaymentService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
        protected PaymentService $paymentService,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $wallet = $this->walletService->walletFor($request->user());

        return $this->success(__('Wallet fetched successfully.'), $this->walletPayload($wallet));
    }

    public function transactions(Request $request): JsonResponse
    {
        $wallet = $this->walletService->walletFor($request->user());

        $transactions = $wallet->transactions()
            ->when($request->filled('type'), function ($query) use ($request): void {
                $type = strtoupper((string) $request->string('type'));
                if (WalletTransactionType::tryFrom($type)) {
                    $query->where('type', $type);
                }
            })
            ->when($request->filled('direction'), function ($query) use ($request): void {
                $direction = strtolower((string) $request->input('direction'));
                if ($direction === 'credit') {
                    $query->whereIn('type', array_map(
                        fn (WalletTransactionType $type): string => $type->value,
                        array_filter(WalletTransactionType::cases(), fn (WalletTransactionType $type): bool => $type->isCredit()),
                    ));
                } elseif ($direction === 'debit') {
                    $query->whereIn('type', array_map(
                        fn (WalletTransactionType $type): string => $type->value,
                        array_filter(WalletTransactionType::cases(), fn (WalletTransactionType $type): bool => $type->isDebit()),
                    ));
                }
            })
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(__('Wallet transactions fetched successfully.'), $transactions, fn ($items) => $items->map(fn ($tx) => $this->walletTransactionPayload($tx)));
    }

    public function show(Request $request, WalletTransaction $transaction): JsonResponse
    {
        $this->authorizeOwnership($request, $transaction);

        return $this->success(__('Wallet transaction fetched successfully.'), $this->walletTransactionPayload($transaction));
    }

    public function recharge(AddWalletMoneyRequest $request): JsonResponse
    {
        try {
            $payment = $this->paymentService->createRechargePayment($request->user(), (float) $request->validated()['amount']);
        } catch (\Throwable $e) {
            return $this->error(
                __('Unable to start the wallet recharge: :message', ['message' => $e->getMessage()]),
                null,
                422,
            );
        }

        if (empty($payment['razorpay']['key'] ?? null)) {
            return $this->error(__('Razorpay is not configured. Please add your API keys in Payment Settings.'), null, 422);
        }

        return $this->success(__('Wallet recharge initiated.'), [
            'razorpay' => $payment['razorpay'],
        ], 201);
    }

    public function confirmRecharge(VerifyRechargePaymentRequest $request): JsonResponse
    {
        $payment = Payment::where('user_id', $request->user()->id)
            ->whereNull('order_id')
            ->where('reference_type', Wallet::class)
            ->where('status', 'PENDING')
            ->whereJsonContains('payload->id', (string) $request->string('razorpay_order_id'))
            ->latest('id')
            ->first();

        if (! $payment) {
            return $this->error(__('Wallet recharge payment not found.'), null, 404);
        }

        $verified = $this->paymentService->verifyAndCaptureRecharge($payment, $request->validated());

        if (! $verified) {
            return $this->error(__('Payment verification failed.'), null, 400);
        }

        $wallet = $this->walletService->walletFor($request->user());

        return $this->success(__('Wallet recharged successfully.'), $this->walletPayload($wallet));
    }

    protected function authorizeOwnership(Request $request, WalletTransaction $transaction): void
    {
        abort_if($transaction->wallet->user_id !== $request->user()->id, 404, 'Wallet transaction not found.');
    }

    protected function walletPayload(Wallet $wallet): array
    {
        return [
            'id' => $wallet->id,
            'currency' => $wallet->currency,
            'available_balance' => $wallet->available_balance,
            'pending_balance' => $wallet->pending_balance,
            'total_balance' => $wallet->totalBalance(),
            'status' => $wallet->status->value,
        ];
    }

    protected function walletTransactionPayload(WalletTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'wallet_id' => $transaction->wallet_id,
            'type' => $transaction->type,
            'amount' => (float) $transaction->amount,
            'balance_before' => (float) $transaction->balance_before,
            'balance_after' => (float) $transaction->balance_after,
            'status' => $transaction->status->value,
            'description' => $transaction->description,
            'reference_type' => $transaction->reference_type,
            'reference_id' => $transaction->reference_id,
            'created_at' => $transaction->created_at?->toIso8601String(),
        ];
    }
}
