<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\RequestVendorPayoutRequest;
use App\Models\VendorPayout;
use App\Models\VendorProfile;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Wallet\VendorPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function __construct(
        protected VendorPayoutService $payoutService,
        protected NotificationDispatcher $dispatcher,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $payouts = $this->vendor($request)->payouts()
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return $this->success(__('Payout requests fetched successfully.'), $payouts);
    }

    public function show(Request $request, VendorPayout $payout): JsonResponse
    {
        abort_unless($payout->vendor_id === $this->vendor($request)->id, 404, 'Payout not found.');

        return $this->success(__('Payout fetched successfully.'), $payout->load('vendor.wallet'));
    }

    public function request(RequestVendorPayoutRequest $request): JsonResponse
    {
        $vendor = $this->vendor($request);

        try {
            $payout = $this->payoutService->request(
                $vendor,
                (float) $request->validated('amount'),
                (string) $request->validated('method', 'bank_transfer'),
                $request->validated('account_details'),
                $request->validated('note'),
            );

            $this->dispatcher->notifyAdmins(
                NotificationType::AdminPayoutRequest,
                __('New payout request'),
                __('Vendor ":store" requested a payout of :amount.', [
                    'store' => $vendor->store_name,
                    'amount' => number_format((float) $payout->amount, 2),
                ]),
                ['payout_id' => $payout->id, 'amount' => (float) $payout->amount],
            );

            return $this->success(__('Payout requested successfully.'), $payout->refresh(), 201);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), null, 422);
        }
    }

    private function vendor(Request $request): VendorProfile
    {
        $vendor = $request->user()->vendorProfile;

        abort_unless($vendor, 404, 'Vendor profile not found.');

        return $vendor;
    }
}
