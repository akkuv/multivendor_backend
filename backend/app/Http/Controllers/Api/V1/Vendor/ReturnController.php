<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateReturnStatusRequest;
use App\Models\ProductReturn;
use App\Services\Return\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    public function __construct(protected ReturnService $returnService) {}

    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendorProfile;

        if (! $vendor) {
            return $this->error(__('Vendor profile not found.'), null, 404);
        }

        $returns = ProductReturn::query()
            ->with(['user', 'order', 'orderItem.product', 'refunds'])
            ->whereHas('orderItem', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->upper()),
            )
            ->when(
                $request->filled('search'),
                fn ($q) => $q->whereHas('orderItem', fn ($inner) => $inner->where('product_name', 'like', '%'.$request->string('search').'%')),
            )
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 12));

        return $this->paginated(__('Returns fetched successfully.'), $returns, fn ($items) => $items);
    }

    public function show(Request $request, ProductReturn $productReturn): JsonResponse
    {
        $this->authorize('view', $productReturn);

        $productReturn->load(['user', 'order', 'orderItem.product', 'pickupAddress', 'refunds']);

        return $this->success(__('Return fetched successfully.'), $productReturn);
    }

    /**
     * Confirm, reject or cancel a customer's return request.
     */
    public function updateStatus(UpdateReturnStatusRequest $request, ProductReturn $productReturn): JsonResponse
    {
        $this->authorize('update', $productReturn);

        $target = ReturnStatus::tryFrom(strtoupper((string) $request->string('status')));

        if (! in_array($target, [ReturnStatus::Approved, ReturnStatus::Rejected, ReturnStatus::Closed], true)) {
            return $this->error(__('Invalid return status.'), null, 422);
        }

        try {
            $productReturn = $this->returnService->transitionTo(
                $productReturn,
                $target,
                $request->input('notes'),
                $request->boolean('auto_refund', true),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        return $this->success(__('Return status updated successfully.'), $productReturn->fresh(['refunds']));
    }
}
