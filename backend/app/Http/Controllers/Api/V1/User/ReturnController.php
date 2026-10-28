<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestReturnRequest;
use App\Models\OrderItem;
use App\Models\ProductReturn;
use App\Services\Return\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    public function __construct(protected ReturnService $returnService) {}

    public function store(RequestReturnRequest $request): JsonResponse
    {
        $orderItem = OrderItem::with('order')
            ->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($request->integer('order_item_id'));

        try {
            $productReturn = $this->returnService->request(
                $request->user(),
                $orderItem->order,
                $request->integer('order_item_id'),
                $request->validated(),
            );

            return $this->success(__('Return requested successfully.'), $productReturn, 201);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), null, 422);
        }
    }

    public function withdraw(Request $request, ProductReturn $productReturn): JsonResponse
    {
        if ($productReturn->user_id !== $request->user()->id) {
            return $this->error(__('Return not found.'), null, 404);
        }

        try {
            $productReturn = $this->returnService->withdraw($productReturn);

            return $this->success(__('Return request withdrawn.'), $productReturn);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $returns = $request->user()->orders()
            ->with('returns')
            ->whereHas('returns')
            ->orderByDesc('created_at')
            ->get()
            ->pluck('returns')
            ->flatten();

        return $this->success(__('Returns fetched successfully.'), $returns);
    }

    public function show(Request $request, ProductReturn $productReturn): JsonResponse
    {
        if ($productReturn->user_id !== $request->user()->id) {
            return $this->error(__('Return not found.'), null, 404);
        }

        $productReturn->load('orderItem', 'order');

        return $this->success(__('Return fetched successfully.'), $productReturn);
    }
}
