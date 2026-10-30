<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistSuggestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class WishlistInsightController extends Controller
{
    /**
     * Aggregate wishlist statistics for the admin dashboard.
     */
    public function stats(): JsonResponse
    {
        $total = WishlistItem::count();
        $active = WishlistItem::where('status', 'active')->count();
        $removed = WishlistItem::where('status', 'removed')->count();
        $customers = WishlistItem::distinct('user_id')->count('user_id');
        $products = WishlistItem::distinct('product_id')->count('product_id');

        $mostWishlisted = WishlistItem::query()
            ->select('product_id', DB::raw('COUNT(*) as wishlist_count'))
            ->with('product:id,name,slug,price,images,vendor_id')
            ->groupBy('product_id')
            ->orderByDesc('wishlist_count')
            ->limit(10)
            ->get();

        $recent = WishlistItem::query()
            ->with(['user:id,name,email,phone', 'product:id,name,slug,price,images'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return $this->success(__('Wishlist insights fetched successfully.'), [
            'total_entries' => $total,
            'active_entries' => $active,
            'removed_entries' => $removed,
            'customers_with_wishlist' => $customers,
            'unique_products' => $products,
            'most_wishlisted' => $mostWishlisted,
            'recent' => $recent,
        ]);
    }

    /**
     * Paginated wishlist records with search/filter/sort.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WishlistItem::query()
            ->with([
                'user:id,name,email,phone',
                'product:id,name,slug,sku,price,images,vendor_id,status',
                'product.vendor:id,store_name',
            ]);

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term): void {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term))
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', $term)
                        ->orWhere('sku', 'like', $term));
            });
        }

        if ($request->filled('vendor_id')) {
            $query->whereHas('product', fn ($p) => $p->where('vendor_id', $request->integer('vendor_id')));
        }

        if ($request->filled('category_id')) {
            $query->whereHas('product', fn ($p) => $p->where('category_id', $request->integer('category_id')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to'));
        }

        $sort = $request->string('sort', 'newest');
        match ($sort) {
            'oldest' => $query->orderBy('created_at'),
            'price_asc' => $query->join('products', 'products.id', '=', 'wishlist_items.product_id')->orderBy('products.price'),
            'price_desc' => $query->join('products', 'products.id', '=', 'wishlist_items.product_id')->orderByDesc('products.price'),
            default => $query->orderByDesc('created_at'),
        };

        $items = $query->paginate($request->integer('per_page', 20));

        return $this->paginated(
            __('Wishlist records fetched successfully.'),
            $items,
            fn ($rows) => $rows->map(fn (WishlistItem $item) => [
                'id' => $item->id,
                'status' => $item->status,
                'price_at_add' => $item->price_at_add,
                'added_at' => $item->created_at,
                'removed_at' => $item->removed_at,
                'customer' => $item->user ? [
                    'id' => $item->user->id,
                    'name' => $item->user->name,
                    'email' => $item->user->email,
                    'phone' => $item->user->phone,
                ] : null,
                'product' => $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'slug' => $item->product->slug,
                    'sku' => $item->product->sku,
                    'price' => $item->product->price,
                    'image' => $item->product->images[0] ?? null,
                    'vendor' => $item->product->vendor?->store_name,
                    'vendor_id' => $item->product->vendor_id,
                ] : null,
            ]),
        );
    }

    /**
     * Customer-specific wishlist details + history + suggestions.
     */
    public function customerDetail(int $userId): JsonResponse
    {
        $customer = User::findOrFail($userId);

        $items = WishlistItem::query()
            ->where('user_id', $userId)
            ->with(['product:id,name,slug,sku,price,images,vendor_id', 'product.vendor:id,store_name'])
            ->orderByDesc('created_at')
            ->get();

        $suggestions = WishlistSuggestion::query()
            ->where('customer_user_id', $userId)
            ->with(['product:id,name,slug,price,images', 'admin:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return $this->success(__('Customer wishlist details fetched successfully.'), [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ],
            'active' => $items->where('status', 'active')->values(),
            'history' => $items,
            'suggestions' => $suggestions,
            'counts' => [
                'active' => $items->where('status', 'active')->count(),
                'total' => $items->count(),
            ],
        ]);
    }

    /**
     * Recommended products for a wishlisted product.
     */
    public function recommendations(Request $request, int $productId): JsonResponse
    {
        $product = Product::with('category', 'brand', 'vendor')->findOrFail($productId);

        $recommended = Product::query()
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->when($product->vendor_id, fn ($q, $v) => $q->orWhere('vendor_id', $v))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'name', 'slug', 'price', 'images', 'vendor_id', 'category_id', 'stock']);

        $result = $recommended->map(function (Product $p) use ($product): array {
            $reasons = [];
            if ($p->category_id === $product->category_id) {
                $reasons[] = 'Same category';
            }
            if ($p->vendor_id === $product->vendor_id) {
                $reasons[] = 'Same vendor';
            }
            if (abs((float) $p->price - (float) $product->price) <= (float) $product->price * 0.3) {
                $reasons[] = 'Similar price';
            }
            if ($p->stock > 0) {
                $reasons[] = 'In stock';
            }

            return [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'price' => $p->price,
                'image' => $p->images[0] ?? null,
                'vendor_id' => $p->vendor_id,
                'reasons' => $reasons ?: ['Popular product'],
            ];
        });

        return $this->success(__('Recommendations fetched successfully.'), $result);
    }

    /**
     * Send a personalized suggestion to a customer.
     */
    public function sendSuggestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_user_id' => ['required', 'integer', 'exists:users,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'recommended_product_ids' => ['required', 'array', 'min:1'],
            'recommended_product_ids.*' => ['integer', 'exists:products,id'],
            'message' => ['nullable', 'string', 'max:1000'],
            'channel' => ['nullable', 'string', 'in:email,notification,whatsapp'],
        ]);

        $customer = User::findOrFail($validated['customer_user_id']);
        $product = Product::findOrFail($validated['product_id']);
        $recommended = Product::whereIn('id', $validated['recommended_product_ids'])->get();

        $channel = $validated['channel'] ?? 'email';
        $message = $validated['message'] ?? "Hello {$customer->name},\n\nWe noticed that you are interested in {$product->name}.\n\nWe thought you might also like these products from our store.\n\nCheck out these recommendations and enjoy shopping!\n\nThank you for shopping with us.";

        $status = 'sent';

        // Website notification
        if (in_array($channel, ['notification', 'email'], true)) {
            $customer->notifications()->create([
                'type' => 'App\\Notifications\\AppNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $customer->id,
                'data' => json_encode([
                    'type' => 'wishlist_suggestion',
                    'title' => 'Product recommendations for you',
                    'message' => $message,
                    'products' => $recommended->map(fn ($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'price' => $p->price,
                        'image' => $p->images[0] ?? null,
                        'slug' => $p->slug,
                    ])->values()->all(),
                ]),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Email (queued if queue worker available)
        if ($channel === 'email') {
            try {
                Mail::raw($message, function ($mail) use ($customer, $product) {
                    $mail->to($customer->email)->subject("Recommendations based on {$product->name}");
                });
            } catch (\Throwable $e) {
                $status = 'failed';
            }
        }

        $suggestion = WishlistSuggestion::create([
            'wishlist_item_id' => WishlistItem::where('user_id', $customer->id)->where('product_id', $product->id)->value('id'),
            'customer_user_id' => $customer->id,
            'product_id' => $product->id,
            'admin_user_id' => $request->user()->id,
            'message' => $message,
            'channel' => $channel,
            'status' => $status,
            'sent_at' => now(),
        ]);

        return $this->success(__('Suggestion sent successfully.'), [
            'suggestion' => $suggestion,
            'delivery_status' => $status,
        ]);
    }

    /**
     * Suggestion delivery history.
     */
    public function suggestionHistory(Request $request): JsonResponse
    {
        $query = WishlistSuggestion::query()
            ->with(['customer:id,name,email', 'product:id,name,slug,price,images', 'admin:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('customer_user_id')) {
            $query->where('customer_user_id', $request->integer('customer_user_id'));
        }

        return $this->paginated(
            __('Suggestion history fetched successfully.'),
            $query->paginate($request->integer('per_page', 20)),
            fn ($rows) => $rows,
        );
    }
}
