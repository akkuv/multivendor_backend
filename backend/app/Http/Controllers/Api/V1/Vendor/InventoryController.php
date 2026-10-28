<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryAdjustmentRequest;
use App\Http\Resources\V1\InventoryTransactionResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(protected InventoryService $inventoryService) {}

    public function index(Request $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $transactions = $product->inventoryTransactions()
            ->with('variant')
            ->when($request->filled('variant_id'), fn ($q) => $q->where('product_variant_id', $request->integer('variant_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->upper()))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(__('Inventory transactions fetched successfully.'), $transactions, fn ($items) => InventoryTransactionResource::collection($items));
    }

    public function adjust(InventoryAdjustmentRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $variant = $this->resolveVariant($product, $request->input('variant_id'));

        $transaction = $this->inventoryService->adjust(
            $product,
            $variant,
            $request->string('type')->value(),
            $request->integer('quantity'),
            $request->input('reason'),
            createdBy: $request->user()->id,
        );

        return $this->success(__('Stock adjusted successfully.'), new InventoryTransactionResource($transaction->load('variant')), 201);
    }

    protected function resolveVariant(Product $product, ?int $variantId): ?ProductVariant
    {
        $hasVariants = $product->variants()->exists();

        if ($hasVariants && ! $variantId) {
            abort(422, __('This product uses variants. Adjust stock on a specific variant instead.'));
        }

        if ($variantId && ! $hasVariants) {
            abort(422, __('This product does not have variants.'));
        }

        if (! $variantId) {
            return null;
        }

        $variant = ProductVariant::where('product_id', $product->id)->find($variantId);

        if (! $variant) {
            abort(404, __('Variant not found for this product.'));
        }

        return $variant;
    }
}
