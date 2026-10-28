<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Enums\VendorStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BrandResource;
use App\Http\Resources\V1\CategoryResource;
use App\Http\Resources\V1\ProductResource;
use App\Http\Resources\V1\StorefrontVendorResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    protected function isMySql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    public function __invoke(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->toString();
        $perPage = $request->integer('per_page', 12);

        $empty = [
            'data' => [],
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
        ];

        if ($term === '') {
            return $this->success(__('Search results fetched successfully.'), [
                'products' => $empty,
                'categories' => [],
                'brands' => [],
                'vendors' => [],
            ]);
        }

        $like = '%'.$term.'%';

        $products = Product::query()
            ->with(['vendor', 'category', 'brand'])
            ->withAvg(['reviews as rating_avg' => fn (Builder $q) => $q->where('is_approved', true)], 'rating')
            ->withCount(['reviews as rating_count' => fn (Builder $q) => $q->where('is_approved', true)])
            ->live()
            ->where(function (Builder $q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('short_description', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('brand', fn (Builder $b) => $b->where('name', 'like', $like))
                    ->orWhereHas('vendor', fn (Builder $v) => $v->where('store_name', 'like', $like));
            })
            ->when($this->isMySql(), function (Builder $q) use ($term): void {
                $q->orderByRaw('MATCH (name, short_description, description, sku) AGAINST (?) DESC', [$term]);
            }, fn (Builder $q) => $q->orderByDesc('created_at'))
            ->paginate($perPage);

        $categories = Category::query()
            ->where('is_active', true)
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(8)
            ->get();

        $brands = Brand::query()
            ->where('is_active', true)
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(8)
            ->get();

        $vendors = VendorProfile::query()
            ->where('is_approved', true)
            ->where('status', VendorStatus::Approved->value)
            ->where('store_name', 'like', $like)
            ->orderBy('store_name')
            ->limit(8)
            ->get();

        return $this->success(__('Search results fetched successfully.'), [
            'products' => [
                'data' => ProductResource::collection($products->items()),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'categories' => CategoryResource::collection($categories),
            'brands' => BrandResource::collection($brands),
            'vendors' => StorefrontVendorResource::collection($vendors),
        ]);
    }
}
