<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\V1\ProductResource;
use App\Models\Product;
use App\Models\VendorProfile;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(protected ProductService $productService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $vendor = $this->vendor($request);

        $products = Product::query()
            ->with('category', 'brand', 'variants', 'images', 'fees', 'collections')
            ->where('vendor_id', $vendor->id)
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 12));

        return $this->paginated(__('Products fetched successfully.'), $products, fn ($items) => ProductResource::collection($items));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $vendor = $this->vendor($request);

        $product = $this->productService->createForVendor($vendor, $request->validated());

        return $this->success(__('Product created successfully.'), new ProductResource($product), 201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $product->load('category', 'subcategory', 'brand', 'images', 'videos', 'variants', 'fees', 'faqs', 'collections');

        return $this->success(__('Product fetched successfully.'), new ProductResource($product));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $vendor = $this->vendor($request);

        $product = $this->productService->updateForVendor($vendor, $product, $request->validated());

        return $this->success(__('Product updated successfully.'), new ProductResource($product->load('category')));
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return $this->success(__('Product deleted successfully.'));
    }

    public function toggleActive(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $product->update(['is_active' => ! $product->is_active]);

        return $this->success(__('Product status updated.'), new ProductResource($product));
    }

    protected function vendor(Request $request): VendorProfile
    {
        $vendor = $request->user()->vendorProfile;

        if (! $vendor) {
            abort(404, __('Vendor profile not found.'));
        }

        return $vendor;
    }
}
