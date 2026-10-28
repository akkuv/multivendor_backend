<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProductOfferResource;
use App\Http\Resources\V1\ProductResource;
use App\Http\Resources\V1\ReviewResource;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = $this->storefrontQuery($request)->paginate($request->integer('per_page', 12));

        $data = [
            'data' => ProductResource::collection($products->items()),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
        ];

        if ($request->boolean('with_facets')) {
            $data['facets'] = $this->facets($this->baseQuery($request));
        }

        return $this->success(__('Products fetched successfully.'), $data);
    }

    public function show(Product $product): JsonResponse
    {
        if (! $product->isLive()) {
            return $this->error(__('Product not found.'), null, 404);
        }

        $product->load(['vendor', 'category', 'subcategory', 'brand', 'images', 'videos', 'variants', 'fees', 'faqs', 'collections']);
        $product->loadAvg(['reviews as rating_avg' => fn (Builder $q) => $q->where('is_approved', true)], 'rating');
        $product->loadCount(['reviews as rating_count' => fn (Builder $q) => $q->where('is_approved', true)]);

        return $this->success(__('Product fetched successfully.'), new ProductResource($product));
    }

    public function offers(Product $product): JsonResponse
    {
        if (! $product->isLive()) {
            return $this->error(__('Product not found.'), null, 404);
        }

        $coupons = Coupon::query()
            ->with(['products', 'categories', 'brands', 'vendors'])
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->where(fn (Builder $q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->get()
            ->filter(fn (Coupon $coupon) => $this->couponAppliesToProduct($coupon, $product))
            ->values();

        return $this->success(__('Offers fetched successfully.'), ProductOfferResource::collection($coupons));
    }

    protected function couponAppliesToProduct(Coupon $coupon, Product $product): bool
    {
        $productIds = $coupon->products->pluck('id');
        if ($productIds->isNotEmpty() && ! $productIds->contains($product->id)) {
            return false;
        }

        $categoryIds = $coupon->categories->pluck('id');
        if ($categoryIds->isNotEmpty() && ! $categoryIds->contains($product->category_id)) {
            return false;
        }

        $brandIds = $coupon->brands->pluck('id');
        if ($brandIds->isNotEmpty() && ! $brandIds->contains($product->brand_id)) {
            return false;
        }

        $vendorIds = $coupon->vendors->pluck('id');
        if ($vendorIds->isNotEmpty()) {
            return $vendorIds->contains($product->vendor_id);
        }

        if ($coupon->vendor_id !== null) {
            return $coupon->vendor_id === $product->vendor_id;
        }

        return true;
    }

    public function featured(Request $request): JsonResponse
    {
        $products = $this->listQuery($request)
            ->where('featured', true)
            ->orderByDesc('rating_avg')
            ->orderByDesc('rating_count')
            ->orderByDesc('created_at')
            ->limit($request->integer('per_page', 12))
            ->get();

        return $this->success(__('Featured products fetched successfully.'), ProductResource::collection($products));
    }

    public function deals(Request $request): JsonResponse
    {
        $products = $this->listQuery($request)
            ->whereNotNull('mrp')
            ->whereColumn('mrp', '>', 'price')
            ->orderByRaw('CASE WHEN mrp > price THEN ((mrp - price) / mrp) * 100 ELSE 0 END DESC')
            ->limit($request->integer('per_page', 8))
            ->get();

        return $this->success(__('Deals fetched successfully.'), ProductResource::collection($products));
    }

    public function bestSellers(Request $request): JsonResponse
    {
        $products = $this->listQuery($request)
            ->where('best_seller', true)
            ->orderByDesc('rating_count')
            ->orderByDesc('created_at')
            ->limit($request->integer('per_page', 8))
            ->get();

        return $this->success(__('Best sellers fetched successfully.'), ProductResource::collection($products));
    }

    public function trending(Request $request): JsonResponse
    {
        $products = $this->listQuery($request)
            ->where(fn (Builder $q) => $q->where('stock', '>', 0)
                ->orWhereHas('variants', fn (Builder $v) => $v->where('status', 'ACTIVE')->where('stock', '>', 0)))
            ->orderByDesc('rating_avg')
            ->orderByDesc('rating_count')
            ->orderByDesc('created_at')
            ->limit($request->integer('per_page', 8))
            ->get();

        return $this->success(__('Trending products fetched successfully.'), ProductResource::collection($products));
    }

    public function reviews(Product $product): JsonResponse
    {
        if (! $product->isLive()) {
            return $this->error(__('Product not found.'), null, 404);
        }

        $reviews = $product->approvedReviews()
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        return $this->success(__('Reviews fetched successfully.'), ReviewResource::collection($reviews));
    }

    public function vendors(Request $request, int $vendorId): JsonResponse
    {
        $products = $this->listQuery($request)
            ->where('vendor_id', $vendorId)
            ->whereHas('vendor', fn (Builder $q) => $q->where('is_approved', true))
            ->paginate($request->integer('per_page', 12));

        return $this->paginated(__('Products fetched successfully.'), $products, fn ($items) => ProductResource::collection($items));
    }

    /**
     * Live products with rating aggregates and list relationships loaded.
     */
    protected function listQuery(Request $request): Builder
    {
        return Product::query()
            ->with(['vendor', 'category', 'brand'])
            ->with('images')
            ->withAvg(['reviews as rating_avg' => fn (Builder $q) => $q->where('is_approved', true)], 'rating')
            ->withCount(['reviews as rating_count' => fn (Builder $q) => $q->where('is_approved', true)])
            ->live();
    }

    /**
     * Filters that narrow the catalogue: search + category.
     * Used both for the product query and as the base for generating facets.
     */
    protected function baseQuery(Request $request): Builder
    {
        return $this->listQuery($request)
            ->when($request->filled('search'), fn (Builder $q) => $this->applySearch($q, $request->string('search')->toString()))
            ->when($request->filled('category_id'), fn (Builder $q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('subcategory_id'), fn (Builder $q) => $q->where('subcategory_id', $request->integer('subcategory_id')))
            ->when($request->filled('category_scope_id'), fn (Builder $q) => $this->applyCategoryScopeId($q, $request->integer('category_scope_id')))
            ->when($request->filled('collection_id'), fn (Builder $q) => $q->whereHas('collections', fn (Builder $c) => $c->where('collections.id', $request->integer('collection_id'))))
            ->when($request->filled('vendor_id'), fn (Builder $q) => $q->where('vendor_id', $request->integer('vendor_id')));
    }

    /**
     * Full storefront query: base + facet filters + ordering.
     */
    protected function storefrontQuery(Request $request): Builder
    {
        return $this->baseQuery($request)
            ->when($request->filled('brand_id'), fn (Builder $q) => $q->where('brand_id', $request->integer('brand_id')))
            ->when($request->filled('min_price'), fn (Builder $q) => $q->where('price', '>=', $request->float('min_price')))
            ->when($request->filled('max_price'), fn (Builder $q) => $q->where('price', '<=', $request->float('max_price')))
            ->when($request->filled('min_discount'), fn (Builder $q) => $q->whereRaw('mrp IS NOT NULL AND mrp > price AND ((mrp - price) / mrp) * 100 >= ?', [$request->float('min_discount')]))
            ->when($request->filled('min_rating'), fn (Builder $q) => $q->whereHas(
                'reviews',
                fn (Builder $sub) => $sub->where('is_approved', true)
                    ->selectRaw('product_id, avg(rating) as product_rating')
                    ->groupBy('product_id')
                    ->havingRaw('avg(rating) >= ?', [$request->float('min_rating')])
            ))
            ->when($request->boolean('in_stock'), fn (Builder $q) => $q->where(
                fn (Builder $q2) => $q2->where('stock', '>', 0)
                    ->orWhereHas('variants', fn (Builder $v) => $v->where('status', 'ACTIVE')->where('stock', '>', 0))
            ))
            ->when($request->boolean('featured'), fn (Builder $q) => $q->where('featured', true))
            ->when($request->boolean('best_seller'), fn (Builder $q) => $q->where('best_seller', true))
            ->when($request->boolean('deals_only'), fn (Builder $q) => $q->whereNotNull('mrp')->whereColumn('mrp', '>', 'price'))
            ->when($request->filled('color'), fn (Builder $q) => $this->variantAttributeFilter($q, $request->string('color')->toString()))
            ->when($request->filled('size'), fn (Builder $q) => $this->variantAttributeFilter($q, $request->string('size')->toString()))
            ->when($request->filled('sort'), fn (Builder $q) => $this->applySort($q, $request), fn (Builder $q) => $q->orderByDesc('created_at'));
    }

    /**
     * Narrow the query to products belonging to a category and its descendants.
     */
    protected function applyCategoryScopeId(Builder $query, int $categoryId): void
    {
        $category = Category::find($categoryId);

        if ($category !== null) {
            $query->inCategoryTree($category);
        }
    }

    protected function applySearch(Builder $q, string $term): void
    {
        $like = '%'.$term.'%';

        $q->where(function (Builder $qq) use ($like): void {
            $qq->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('short_description', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('brand', fn (Builder $b) => $b->where('name', 'like', $like))
                ->orWhereHas('vendor', fn (Builder $v) => $v->where('store_name', 'like', $like));
        });
    }

    protected function applySort(Builder $q, Request $request): void
    {
        match ($request->string('sort')->toString()) {
            'price_asc' => $q->orderBy('price', 'asc'),
            'price_desc' => $q->orderBy('price', 'desc'),
            'best_selling' => $q->orderByDesc('best_seller')->orderByDesc('created_at'),
            'rating' => $q->orderByDesc('rating_avg')->orderByDesc('rating_count')->orderByDesc('created_at'),
            'discount' => $q->orderByRaw('CASE WHEN mrp IS NOT NULL AND mrp > price THEN ((mrp - price) / mrp) * 100 ELSE 0 END DESC'),
            'relevance' => $this->applyRelevanceOrder($q, $request->string('search')->toString()),
            default => $q->orderByDesc('created_at'),
        };
    }

    protected function applyRelevanceOrder(Builder $q, string $term): void
    {
        if ($term === '') {
            $q->orderByDesc('created_at');

            return;
        }

        if ($q->getConnection()->getDriverName() === 'mysql') {
            $q->orderByRaw('MATCH (name, short_description, description, sku) AGAINST (?) DESC', [$term]);

            return;
        }

        $q->orderByDesc('created_at');
    }

    protected function variantAttributeFilter(Builder $q, string $value): void
    {
        $driver = $q->getConnection()->getDriverName();

        $q->whereHas('variants', function (Builder $vv) use ($value, $driver): void {
            $vv->where('status', 'ACTIVE');

            if ($driver === 'mysql') {
                $vv->whereRaw('JSON_SEARCH(attributes, \'one\', ?) IS NOT NULL', [$value]);
            } else {
                $vv->where('attributes', 'like', '%'.$value.'%');
            }
        });
    }

    /**
     * Facet aggregates for the current search scope (brands, price range,
     * and filterable variant attributes such as colour and size).
     *
     * @return array<string, mixed>
     */
    protected function facets(Builder $base): array
    {
        $snapshot = clone $base;

        $rows = (clone $snapshot)
            ->whereNotNull('brand_id')
            ->with('brand')
            ->get(['id', 'brand_id', 'price']);

        $brands = $rows->groupBy('brand_id')
            ->map(function ($group, $brandId): ?array {
                $brand = $group->first()->brand;
                if (! $brand) {
                    return null;
                }

                return [
                    'id' => (int) $brandId,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                    'count' => $group->count(),
                ];
            })
            ->filter()
            ->sortByDesc('count')
            ->values();

        $minPrice = (float) (clone $snapshot)->min('price');
        $maxPrice = (float) (clone $snapshot)->max('price');

        $categories = $this->categoryFacets(
            (clone $snapshot)->whereNotNull('category_id')
                ->get(['id', 'category_id'])
                ->groupBy('category_id')
                ->map->count()
        );

        $subcategories = $this->categoryFacets(
            (clone $snapshot)->whereNotNull('subcategory_id')
                ->get(['id', 'subcategory_id'])
                ->groupBy('subcategory_id')
                ->map->count()
        );

        $ids = (clone $snapshot)->limit(5000)->pluck('id');
        $attributeMap = [];

        if ($ids->isNotEmpty()) {
            ProductVariant::query()
                ->whereIn('product_id', $ids)
                ->where('status', 'ACTIVE')
                ->cursor()
                ->each(function (ProductVariant $variant) use (&$attributeMap): void {
                    foreach (($variant->attributes ?? []) as $key => $value) {
                        $label = trim((string) $key);
                        $val = trim((string) $value);

                        if ($label === '' || $val === '') {
                            continue;
                        }

                        $attributeMap[$label][$val][$variant->product_id] = true;
                    }
                });
        }

        $attributes = collect($attributeMap)
            ->map(fn (array $values, string $name) => [
                'name' => $name,
                'values' => collect($values)
                    ->map(fn (array $productIds, string $value) => ['value' => $value, 'count' => count($productIds)])
                    ->sortByDesc('count')
                    ->values()
                    ->take(20)
                    ->all(),
            ])
            ->sortByDesc(fn (array $facet) => count($facet['values']))
            ->take(8)
            ->values();

        return [
            'brands' => $brands,
            'categories' => $categories,
            'subcategories' => $subcategories,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'attributes' => $attributes,
        ];
    }

    /**
     * Turn a `category_id => product count` map into sorted facet options
     * (used for both the "Product Category" and "Sub Categories" facets).
     *
     * @param  Collection<int|string, int|string>  $counts
     * @return Collection<int, array{id: int, name: string, slug: string, count: int}>
     */
    protected function categoryFacets(Collection $counts): Collection
    {
        $ids = $counts->keys()->map(fn ($id): int => (int) $id)->all();

        $models = Category::query()->whereIn('id', $ids)->get()->keyBy('id');

        return $counts
            ->map(function ($total, $id) use ($models): ?array {
                $category = $models->get((int) $id);

                if (! $category) {
                    return null;
                }

                return [
                    'id' => (int) $id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'count' => (int) $total,
                ];
            })
            ->filter()
            ->sortByDesc('count')
            ->values();
    }
}
