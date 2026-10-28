<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Concerns\FiltersCreatedRange;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectProductRequest;
use App\Http\Requests\UpdateProductReturnPolicyRequest;
use App\Http\Resources\V1\ProductResource;
use App\Models\Product;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use FiltersCreatedRange;

    public function __construct(protected ProductService $productService) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->applyCreatedRange(Product::query(), $request)
            ->with('vendor', 'category', 'brand')
            ->when(
                $request->filled('approval_status'),
                fn ($q) => $q->where('approval_status', $request->string('approval_status')->upper()),
            )
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->upper()),
            )
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', $request->integer('vendor_id')))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 12));

        return $this->paginated(__('Products fetched successfully.'), $products, fn ($items) => ProductResource::collection($items));
    }

    public function show(Product $product): JsonResponse
    {
        $product->load('vendor', 'category', 'subcategory', 'brand', 'images', 'videos', 'variants', 'fees', 'faqs', 'collections');

        return $this->success(__('Product fetched successfully.'), new ProductResource($product));
    }

    public function approve(Product $product): JsonResponse
    {
        $product = $this->productService->approve($product);

        return $this->success(__('Product approved successfully.'), new ProductResource($product));
    }

    public function reject(RejectProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->productService->reject($product, $request->input('reason'));

        return $this->success(__('Product rejected.'), new ProductResource($product));
    }

    public function publish(Product $product): JsonResponse
    {
        try {
            $product = $this->productService->publish($product);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        return $this->success(__('Product published successfully.'), new ProductResource($product));
    }

    public function unpublish(Product $product): JsonResponse
    {
        $product = $this->productService->unpublish($product);

        return $this->success(__('Product unpublished successfully.'), new ProductResource($product));
    }

    public function feature(Product $product): JsonResponse
    {
        $product->update(['featured' => ! $product->featured]);

        return $this->success(__('Product featured status updated.'), new ProductResource($product->fresh()->load('vendor', 'category', 'brand')));
    }

    public function updateReturnPolicy(UpdateProductReturnPolicyRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());
        $product = $product->fresh()->load('vendor', 'category', 'brand');

        return $this->success(__('Return policy updated successfully.'), new ProductResource($product));
    }

    public function bestSeller(Product $product): JsonResponse
    {
        $product->update(['best_seller' => ! $product->best_seller]);

        return $this->success(__('Product best-seller status updated.'), new ProductResource($product->fresh()->load('vendor', 'category', 'brand')));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->forceDelete();

        return $this->success(__('Product deleted successfully.'));
    }
}
