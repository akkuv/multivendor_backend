<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProductResource;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RecentlyViewed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerExperienceController extends Controller
{
    /**
     * Recently viewed products for the authenticated customer.
     */
    public function recentlyViewed(Request $request): JsonResponse
    {
        $products = $this->listQuery($request)
            ->whereHas('recentViews', fn (Builder $q) => $q->where('user_id', $request->user()->id))
            ->orderByDesc(
                Product::query()->select('last_viewed_at')
                    ->from('recently_viewed')
                    ->whereColumn('recently_viewed.product_id', 'products.id')
                    ->where('recently_viewed.user_id', $request->user()->id)
                    ->limit(1)
            )
            ->limit($request->integer('per_page', 12))
            ->get();

        return $this->success(__('Recently viewed products fetched successfully.'), ProductResource::collection($products));
    }

    /**
     * Record a product view for the authenticated customer.
     */
    public function recordView(Request $request, Product $product): JsonResponse
    {
        if (! $product->isLive()) {
            return $this->error(__('Product not found.'), null, 404);
        }

        RecentlyViewed::record($request->user(), $product);

        return $this->success(__('Product view recorded successfully.'));
    }

    /**
     * Products the customer purchased before that are still live (buy again).
     */
    public function buyAgain(Request $request): JsonResponse
    {
        $limit = $request->integer('per_page', 12);

        $productIds = OrderItem::query()
            ->whereHas('order', function (Builder $q) use ($request): void {
                $q->where('user_id', $request->user()->id)
                    ->whereIn('order_status', [OrderStatus::Delivered->value, OrderStatus::Completed->value]);
            })
            ->whereNotNull('product_id')
            ->orderByDesc('order_items.created_at')
            ->limit(100)
            ->pluck('product_id')
            ->unique()
            ->take($limit)
            ->values();

        $products = $this->listQuery($request)
            ->whereIn('id', $productIds)
            ->get()
            ->sortBy(fn (Product $p) => array_search($p->id, $productIds->all()))
            ->values();

        return $this->success(__('Buy again products fetched successfully.'), ProductResource::collection($products));
    }

    /**
     * Personalized recommendations; falls back to trending when the customer
     * has no order history.
     */
    public function recommendations(Request $request): JsonResponse
    {
        $limit = $request->integer('per_page', 12);

        $categoryIds = OrderItem::query()
            ->whereHas('order', function (Builder $q) use ($request): void {
                $q->where('user_id', $request->user()->id)
                    ->whereIn('order_status', [OrderStatus::Delivered->value, OrderStatus::Completed->value]);
            })
            ->whereHas('product')
            ->get()
            ->flatMap(fn (OrderItem $item) => $item->product?->category_id)
            ->filter()
            ->unique()
            ->values();

        $query = $this->listQuery($request);

        if ($categoryIds->isNotEmpty()) {
            if ($categoryIds->count() === 1) {
                $recommended = $query->clone()
                    ->where('category_id', $categoryIds->first())
                    ->orderByDesc('rating_avg')
                    ->orderByDesc('rating_count')
                    ->limit($limit)
                    ->get();
            } else {
                $recommended = $query->clone()
                    ->whereIn('category_id', $categoryIds)
                    ->orderByRaw(
                        DB::raw(
                            'FIELD(category_id, '.implode(',', $categoryIds->map(fn ($id) => (int) $id)->all()).')'
                        )
                    )
                    ->orderByDesc('rating_avg')
                    ->limit($limit)
                    ->get();
            }

            if ($recommended->count() < $limit && $limit > 1) {
                $extra = $this->listQuery($request)
                    ->whereNotIn('id', $recommended->pluck('id'))
                    ->orderByDesc('rating_avg')
                    ->orderByDesc('rating_count')
                    ->limit($limit - $recommended->count())
                    ->get();
                $recommended = $recommended->concat($extra);
            }

            return $this->success(__('Recommended products fetched successfully.'), ProductResource::collection($recommended));
        }

        $fallback = $query->orderByDesc('rating_avg')
            ->orderByDesc('rating_count')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $this->success(__('Recommended products fetched successfully.'), ProductResource::collection($fallback));
    }

    /**
     * Related products sharing the same category as the given product.
     */
    public function related(Request $request, Product $product): JsonResponse
    {
        if (! $product->isLive()) {
            return $this->error(__('Product not found.'), null, 404);
        }

        $products = $this->listQuery($request)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->orderByDesc('rating_avg')
            ->orderByDesc('rating_count')
            ->limit($request->integer('per_page', 8))
            ->get();

        return $this->success(__('Related products fetched successfully.'), ProductResource::collection($products));
    }

    /**
     * Products frequently purchased alongside the given product.
     */
    public function frequentlyBoughtTogether(Request $request, Product $product): JsonResponse
    {
        if (! $product->isLive()) {
            return $this->error(__('Product not found.'), null, 404);
        }

        $orderIds = OrderItem::query()
            ->where('product_id', $product->id)
            ->whereHas('order', fn (Builder $q) => $q->whereIn(
                'order_status',
                [OrderStatus::Delivered->value, OrderStatus::Completed->value]
            ))
            ->pluck('order_id')
            ->unique()
            ->take(500);

        $ids = $orderIds->isNotEmpty()
            ? OrderItem::query()
                ->whereIn('order_id', $orderIds)
                ->where('product_id', '!=', $product->id)
                ->whereNotNull('product_id')
                ->select('product_id', DB::raw('COUNT(*) as co_count'))
                ->groupBy('product_id')
                ->orderByDesc('co_count')
                ->limit($request->integer('per_page', 8))
                ->pluck('product_id')
            : collect();

        $products = $this->listQuery($request)
            ->whereIn('id', $ids)
            ->get();

        return $this->success(__('Frequently bought together products fetched successfully.'), ProductResource::collection($products));
    }

    /**
     * Compare a set of products by id.
     */
    public function compare(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->string('ids')))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->take(5)
            ->values();

        if ($ids->isEmpty()) {
            return $this->error(__('Provide at least one product id to compare.'), null, 422);
        }

        $products = $this->listQuery($request)
            ->whereIn('id', $ids)
            ->get();

        return $this->success(__('Comparison fetched successfully.'), [
            'order' => $ids->all(),
            'products' => ProductResource::collection($products),
        ]);
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
}
