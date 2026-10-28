<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Concerns\FiltersCreatedRange;
use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Services\Refund\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    use FiltersCreatedRange;

    public function __construct(protected RefundService $refundService) {}

    public function index(Request $request): JsonResponse
    {
        $refunds = $this->applyCreatedRange(Refund::query(), $request)
            ->with('order', 'user')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->success(__('Refunds fetched successfully.'), $refunds);
    }

    public function process(Refund $refund): JsonResponse
    {
        try {
            $refund = $this->refundService->process($refund);

            return $this->success(__('Refund processed successfully.'), [
                'refund' => $refund,
                'refunded_to' => $refund->method ?? null,
            ]);
        } catch (\Throwable $e) {
            return $this->error(__('Unable to process refund: ').$e->getMessage(), null, 422);
        }
    }
}
