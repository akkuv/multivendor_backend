<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\ReturnStatus;
use App\Http\Controllers\Concerns\FiltersCreatedRange;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateReturnStatusRequest;
use App\Models\ProductReturn;
use App\Services\Return\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    use FiltersCreatedRange;

    public function __construct(protected ReturnService $returnService) {}

    public function index(Request $request): JsonResponse
    {
        $returns = $this->applyCreatedRange(ProductReturn::query(), $request)
            ->with(['user', 'order', 'orderItem.product', 'refunds'])
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->upper()),
            )
            ->when($request->filled('order_number'), fn ($q) => $q->whereHas(
                'order',
                fn ($q) => $q->where('order_number', 'like', '%'.$request->string('order_number').'%'),
            ))
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($q) => $q
                    ->where('reason', 'like', '%'.$request->string('search').'%')
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%')),
            ))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 12));

        // Counts ignore the status/search filters on purpose: the page's status
        // cards are a breakdown of everything in range, and clicking one of them
        // applies that status as a filter.
        $summaryQuery = $this->applyCreatedRange(ProductReturn::query(), $request);

        // `in_range_total`, not `total`: `paginated()` merges these alongside the
        // page, and a plain `total` would clobber the paginator's filtered total
        // (a status-filtered page of 5 would then report 50).
        $summary = [
            'in_range_total' => (clone $summaryQuery)->count(),
            'refunded' => (clone $summaryQuery)->where('status', ReturnStatus::Refunded->value)->count(),
            'by_status' => (clone $summaryQuery)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all(),
        ];

        return $this->paginated(__('Returns fetched successfully.'), $returns, fn ($items) => $items, $summary);
    }

    public function show(ProductReturn $productReturn): JsonResponse
    {
        $productReturn->load(['user', 'order', 'orderItem.product', 'pickupAddress', 'refunds']);

        return $this->success(__('Return fetched successfully.'), $productReturn);
    }

    public function updateStatus(UpdateReturnStatusRequest $request, ProductReturn $productReturn): JsonResponse
    {
        $target = ReturnStatus::tryFrom(strtoupper((string) $request->string('status')));

        try {
            $productReturn = $this->returnService->transitionTo(
                $productReturn,
                $target,
                $request->string('notes')->value() ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        return $this->success(__('Return status updated successfully.'), $productReturn->fresh(['refunds']));
    }
}
