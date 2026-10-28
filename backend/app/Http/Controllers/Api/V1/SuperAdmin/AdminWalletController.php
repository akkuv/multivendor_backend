<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWalletController extends Controller
{
    public function __construct(protected WalletService $walletService) {}

    public function dashboard(Request $request): JsonResponse
    {
        $wallet = $this->walletService->walletFor($request->user());

        return $this->success(__('Admin wallet fetched successfully.'), $this->walletPayload($wallet));
    }

    public function transactions(Request $request): JsonResponse
    {
        $wallet = $this->walletService->walletFor($request->user());

        $query = $wallet->transactions();

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('q')) {
            $search = $request->string('q');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', '%'.$search.'%')
                    ->orWhere('reference_type', 'like', '%'.$search.'%');
            });
        }

        $transactions = $query
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(
            'Admin wallet transactions fetched successfully.',
            $transactions,
            fn ($items) => $items->map(fn (WalletTransaction $tx) => $this->walletTransactionPayload($tx)),
        );
    }

    public function show(Request $request, WalletTransaction $transaction): JsonResponse
    {
        $wallet = $this->walletService->walletFor($request->user());

        abort_if($transaction->wallet_id !== $wallet->id, 404, 'Wallet transaction not found.');

        return $this->success(
            'Admin wallet transaction fetched successfully.',
            $this->walletTransactionDetailPayload($transaction),
        );
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

    protected function walletTransactionDetailPayload(WalletTransaction $transaction): array
    {
        return array_merge($this->walletTransactionPayload($transaction), [
            'wallet' => $this->walletPayload($transaction->wallet),
            'reference' => $this->referencePayload($transaction),
        ]);
    }

    protected function referencePayload(WalletTransaction $transaction): ?array
    {
        if ($transaction->reference_type === null) {
            return null;
        }

        $reference = $transaction->reference()->first();

        if ($reference === null) {
            return null;
        }

        return [
            'type' => class_basename($reference),
            'label' => $this->referenceLabel($reference),
            'summary' => $this->referenceSummary($reference),
        ];
    }

    protected function referenceLabel(object $reference): string
    {
        return match (true) {
            $reference instanceof Order => $reference->order_number,
            $reference instanceof User => $reference->name,
            $reference instanceof VendorProfile => $reference->store_name,
            default => class_basename($reference),
        };
    }

    protected function referenceSummary(object $reference): string
    {
        if ($reference instanceof User) {
            return implode(' ', array_filter([$reference->name, $reference->email]));
        }

        if ($reference instanceof Order) {
            return $reference->user?->name ?? '';
        }

        return '';
    }
}
