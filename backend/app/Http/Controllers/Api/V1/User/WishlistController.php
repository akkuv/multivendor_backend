<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Wishlist\UpdateNotificationsRequest;
use App\Http\Requests\MoveWishlistToCartRequest;
use App\Http\Resources\V1\CartResource;
use App\Http\Resources\V1\WishlistNotificationResource;
use App\Http\Resources\V1\WishlistResource;
use App\Models\Product;
use App\Models\WishlistItem;
use App\Services\Cart\CartService;
use App\Services\Cart\InvalidCartException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function __construct(protected CartService $cartService) {}

    public function index(Request $request): JsonResponse
    {
        $wishlist = $request->user()
            ->wishlistItems()
            ->active()
            ->with([
                'product' => fn ($q) => $q
                    ->with('vendor', 'category', 'brand')
                    ->withCount(['reviews as rating_count' => fn ($r) => $r->where('is_approved', true)])
                    ->withAvg(['reviews as rating_avg' => fn ($r) => $r->where('is_approved', true)], 'rating'),
            ])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated(
            'Wishlist fetched successfully.',
            $wishlist,
            fn ($items) => WishlistResource::collection($items),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $product = Product::query()->findOrFail($validated['product_id']);

        if (! $product->isLive()) {
            return $this->error(__('This product is no longer available.'), null, 404);
        }

        $item = $request->user()
            ->wishlistItems()
            ->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'price_at_add' => $product->price,
                    'status' => WishlistItem::STATUS_ACTIVE,
                    'removed_at' => null,
                ],
            );

        $item->loadMissing('product.vendor', 'product.category', 'product.brand');

        return $this->success(__('Product added to wishlist.'), new WishlistResource($item));
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $request->user()
            ->wishlistItems()
            ->where('product_id', $product->id)
            ->update([
                'status' => WishlistItem::STATUS_REMOVED,
                'removed_at' => now(),
            ]);

        return $this->success(__('Product removed from wishlist.'));
    }

    public function clear(Request $request): JsonResponse
    {
        $request->user()->wishlistItems()->active()->update([
            'status' => WishlistItem::STATUS_REMOVED,
            'removed_at' => now(),
        ]);

        return $this->success(__('Wishlist cleared successfully.'));
    }

    public function moveToCart(MoveWishlistToCartRequest $request, Product $product): JsonResponse
    {
        try {
            $cart = $this->cartService->addItem(
                $request->user(),
                $product->id,
                $request->input('variant_id') !== null ? $request->integer('variant_id') : null,
                $request->integer('quantity', 1),
            );

            $request->user()->wishlistItems()->where('product_id', $product->id)->update([
                'status' => WishlistItem::STATUS_REMOVED,
                'removed_at' => now(),
            ]);

            return $this->success(__('Product moved to cart.'), new CartResource($cart));
        } catch (InvalidCartException $e) {
            return $this->error($e->getMessage(), null, 422);
        }
    }

    public function updateNotifications(UpdateNotificationsRequest $request, Product $product): JsonResponse
    {
        $item = $request->user()
            ->wishlistItems()
            ->where('product_id', $product->id)
            ->first();

        if (! $item) {
            return $this->error(__('This product is not in your wishlist.'), null, 404);
        }

        $item->forceFill([
            'notify_price_drops' => $request->boolean('notify_price_drops'),
            'notify_back_in_stock' => $request->boolean('notify_back_in_stock'),
        ])->save();

        $item->loadMissing('product.vendor', 'product.category', 'product.brand');

        return $this->success(__('Wishlist notification preferences updated.'), new WishlistResource($item));
    }

    public function notifications(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->wishlistNotifications()
            ->with('product')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated(
            'Wishlist notifications fetched successfully.',
            $notifications,
            fn ($items) => WishlistNotificationResource::collection($items),
        );
    }

    public function markNotificationsRead(Request $request): JsonResponse
    {
        $request->user()->wishlistNotifications()->where('is_read', false)->update(['is_read' => true]);

        return $this->success(__('All wishlist notifications marked as read.'));
    }
}
