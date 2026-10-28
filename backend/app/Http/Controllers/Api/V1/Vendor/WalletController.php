<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorProfile;
use App\Services\Order\VendorSettlementService;
use App\Services\Wallet\VendorWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        protected VendorWalletService $walletService,
        protected VendorSettlementService $settlementService,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $vendor = $this->vendor($request);

        $wallet = $this->walletService->walletFor($vendor);

        $transactions = $wallet->transactions()
            ->with('reference')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success(__('Wallet dashboard.'), [
            'wallet' => [
                'available_balance' => $wallet->available_balance,
                'pending_balance' => $wallet->pending_balance,
                'held_balance' => $wallet->held_balance,
                'paid_total' => $wallet->paid_total,
                'total_balance' => $this->money($wallet->totalBalance()),
                'currency' => $wallet->currency,
            ],
            'pending_settlement' => $this->money($this->settlementService->pendingSettlementAmount($vendor)),
            'transactions' => $transactions,
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        $wallet = $this->walletService->walletFor($this->vendor($request));

        return $this->success(__('Wallet balance.'), [
            'available_balance' => $wallet->available_balance,
            'pending_balance' => $wallet->pending_balance,
            'held_balance' => $wallet->held_balance,
            'total_balance' => $this->money($wallet->totalBalance()),
            'currency' => $wallet->currency,
            'status' => $wallet->status->value,
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $wallet = $this->walletService->walletFor($this->vendor($request));

        $transactions = $wallet->transactions()
            ->with('reference')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->success(__('Wallet transactions fetched successfully.'), $transactions);
    }

    private function vendor(Request $request): VendorProfile
    {
        $vendor = $request->user()->vendorProfile;

        abort_unless($vendor, 404, 'Vendor profile not found.');

        return $vendor;
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
