<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Http\Resources\V1\BrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $brands = Brand::query()
            ->with('categories')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('sort_by'), fn ($q) => $q->orderBy($this->sortColumn($request), $this->sortDirection($request)))
            ->when($request->missing('sort_by'), fn ($q) => $q->orderByDesc('created_at'))
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(__('Brands fetched successfully.'), $brands, fn ($items) => BrandResource::collection($items));
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $brand = Brand::create($request->validated());
        $brand->categories()->sync($request->input('category_ids', []));

        return $this->success(__('Brand created successfully.'), new BrandResource($brand->load('categories')), 201);
    }

    public function show(Brand $brand): JsonResponse
    {
        return $this->success(__('Brand fetched successfully.'), new BrandResource($brand->load('categories')));
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $brand->update($request->validated());

        if ($request->has('category_ids')) {
            $brand->categories()->sync($request->input('category_ids', []));
        }

        return $this->success(__('Brand updated successfully.'), new BrandResource($brand->fresh(['categories'])));
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $brand->delete();

        return $this->success(__('Brand deleted successfully.'));
    }

    public function toggleActive(Brand $brand): JsonResponse
    {
        $brand->update(['is_active' => ! $brand->is_active]);

        return $this->success(__('Brand status updated.'), new BrandResource($brand->fresh()));
    }

    protected function sortColumn(Request $request): string
    {
        return in_array($request->string('sort_by'), ['name', 'id', 'created_at', 'updated_at'], true)
            ? $request->string('sort_by')
            : 'created_at';
    }

    protected function sortDirection(Request $request): string
    {
        return strtolower($request->string('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
    }
}
