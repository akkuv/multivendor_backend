<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\NotificationType;
use App\Enums\VendorPayoutStatus;
use App\Http\Controllers\Concerns\FiltersCreatedRange;
use App\Http\Controllers\Controller;
use App\Models\VendorCommission;
use App\Models\VendorPayout;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Order\VendorSettlementService;
use App\Services\Wallet\VendorPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    use FiltersCreatedRange;

    public function __construct(
        protected VendorSettlementService $settlementService,
        protected VendorPayoutService $payoutService,
        protected NotificationDispatcher $dispatcher,
    ) {}

    public function commissions(Request $request): JsonResponse
    {
        $commissions = $this->applyCreatedRange(VendorCommission::query(), $request)
            ->with(['vendor.user', 'vendorOrder'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', $request->integer('vendor_id')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        $summary = [
            'pending' => VendorCommission::where('status', 'PENDING')->count(),
            'pending_amount' => round((float) VendorCommission::where('status', 'PENDING')->sum('net_payable'), 2),
            'total_commission' => round((float) VendorCommission::sum('commission_amount'), 2),
            'total_net_payable' => round((float) VendorCommission::sum('net_payable'), 2),
        ];

        return $this->success(__('Commissions fetched successfully.'), [
            'summary' => $summary,
            'commissions' => $commissions,
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $count = $this->settlementService->settleEligible();

        return $this->success("{$count} order(s) settled.", ['settled' => $count]);
    }

    public function reverse(Request $request, VendorCommission $commission): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $commission = $this->settlementService->reverse($commission, (string) $request->string('reason'));

            return $this->success(__('Commission reversed successfully.'), $commission);
        } catch (\Throwable $e) {
            return $this->error(__('Unable to reverse commission: ').$e->getMessage(), null, 422);
        }
    }

    public function payouts(Request $request): JsonResponse
    {
        $payouts = $this->applyCreatedRange(VendorPayout::query(), $request)
            ->with(['vendor.user'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', $request->integer('vendor_id')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        $summary = [
            'pending' => VendorPayout::where('status', VendorPayoutStatus::Pending->value)->count(),
            'approved' => VendorPayout::where('status', VendorPayoutStatus::Approved->value)->count(),
            'paid' => VendorPayout::where('status', VendorPayoutStatus::Paid->value)->count(),
            'rejected' => VendorPayout::where('status', VendorPayoutStatus::Rejected->value)->count(),
            'pending_amount' => (float) VendorPayout::where('status', VendorPayoutStatus::Pending->value)->sum('amount'),
            'total_amount' => (float) VendorPayout::sum('amount'),
        ];

        return $this->success(__('Payouts fetched successfully.'), [
            'summary' => $summary,
            'payouts' => $payouts,
        ]);
    }

    public function approve(Request $request, VendorPayout $payout): JsonResponse
    {
        return $this->review($request, $payout, 'approve');
    }

    public function markPaid(Request $request, VendorPayout $payout): JsonResponse
    {
        return $this->review($request, $payout, 'markPaid');
    }

    public function reject(Request $request, VendorPayout $payout): JsonResponse
    {
        return $this->review($request, $payout, 'reject');
    }

    private function review(Request $request, VendorPayout $payout, string $action): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $note = $data['note'] ?? null;

        try {
            $payout = $this->payoutService->{$action}($payout, $note);

            if ($action === 'approve' && $payout->load('vendor.user')->vendor?->user) {
                $this->dispatcher->send(
                    $payout->vendor->user,
                    NotificationType::PayoutApproved,
                    __('Payout approved'),
                    __('Your payout of :amount has been approved.', ['amount' => number_format((float) $payout->amount, 2)]),
                    ['payout_id' => $payout->id, 'amount' => (float) $payout->amount],
                );
            }

            return $this->success(__('Payout updated successfully.'), $payout->load('vendor.user'));
        } catch (\Throwable $e) {
            return $this->error(__('Unable to update payout: ').$e->getMessage(), null, 422);
        }
    }
}
