<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Enums\SupportConversationKind;
use App\Enums\SupportSenderRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Support\SendSupportMessageRequest;
use App\Http\Requests\Api\V1\Support\StoreProductQueryRequest;
use App\Http\Resources\V1\SupportConversationResource;
use App\Http\Resources\V1\SupportMessageResource;
use App\Models\Product;
use App\Models\SupportConversation;
use App\Models\VendorProfile;
use App\Services\Support\SupportConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The vendor side of product queries: product-linked questions about what this
 * vendor sells, answered alongside the marketplace team in the same thread the
 * customer started. The vendor can also start their own product query about one
 * of their own products, addressed to the team.
 */
class ProductQueryController extends Controller
{
    public function __construct(
        private readonly SupportConversationService $conversations,
    ) {}

    /**
     * Every product query involving this vendor — customers asking about their
     * products and threads this vendor opened itself — newest activity first,
     * each with the product it concerns and how many replies are still unread.
     */
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendorProfile;

        $conversations = SupportConversation::query()
            ->forKind(SupportConversationKind::ProductQuery)
            ->where('vendor_id', $vendor->id)
            ->with([
                'product:id,vendor_id,name,slug,status,images',
                'product.vendor:id,store_name,slug,logo,status',
                'customerUser:id,name,avatar',
                'latestMessage',
            ])
            ->withCount([
                'messages as unread_for_vendor' => fn ($query) => $query->unreadFor(SupportSenderRole::Vendor->value),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return $this->paginated(
            __('Product queries fetched successfully.'),
            $conversations,
            fn ($items) => SupportConversationResource::collection($items),
            ['unread_count' => $this->conversations->productQueryUnreadCountForVendor($vendor)],
        );
    }

    /**
     * Start a product query about one of this vendor's own products, addressed
     * to the marketplace team. The thread is a fresh one every time and the
     * admin team is told a new query has arrived.
     */
    public function create(StoreProductQueryRequest $request): JsonResponse
    {
        $vendor = $request->user()->vendorProfile;

        $product = Product::query()->findOrFail($request->integer('product_id'));

        if ((int) $product->vendor_id !== (int) $vendor->id) {
            throw ValidationException::withMessages([
                'product_id' => __('You can only ask about your own products.'),
            ]);
        }

        $conversation = $this->conversations->createVendorProductQuery(
            $product,
            $vendor,
            $request->string('body')->toString(),
            imageUrl: $request->validated('image_url'),
        );

        $message = $conversation->messages()->latest('id')->first();

        $conversation->load([
            'product:id,vendor_id,name,slug,status,images,price,mrp',
            'product.vendor:id,store_name,slug,logo,status',
            'customerUser:id,name,avatar',
            'vendor:id,store_name,slug,logo,status',
            'latestMessage',
        ]);
        $message?->load('sender:id,name,avatar');

        return $this->success(__('Product query created successfully.'), [
            'conversation' => new SupportConversationResource($conversation),
            'message' => $message === null ? null : new SupportMessageResource($message),
        ], 201);
    }

    public function show(Request $request, SupportConversation $supportConversation): JsonResponse
    {
        $this->guardVendorQuery($supportConversation, $request->user()->vendorProfile);

        $supportConversation->load([
            'product:id,vendor_id,name,slug,status,images,price,mrp',
            'product.vendor:id,store_name,slug,logo,status',
            'customerUser:id,name,avatar',
            'vendor:id,store_name,slug,logo,status',
            'customerOrders' => fn ($query) => $query
                ->whereHas('items', fn ($items) => $items->where('product_id', $supportConversation->product_id))
                ->with(['items:id,order_id,product_id,quantity,vendor_id,vendor_order_id']),
        ]);

        $messages = $supportConversation->messages()
            ->with('sender:id,name,avatar')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 50));

        return $this->success(__('Product query fetched successfully.'), [
            'conversation' => new SupportConversationResource($supportConversation),
            'messages' => $this->paginatedPayload(
                $messages,
                fn ($items) => SupportMessageResource::collection($items),
            ),
        ]);
    }

    /**
     * A vendor reply joins the thread as the vendor, so the customer and the
     * admin team both see who answered.
     */
    public function store(SendSupportMessageRequest $request, SupportConversation $supportConversation): JsonResponse
    {
        $this->guardVendorQuery($supportConversation, $request->user()->vendorProfile);

        $message = $this->conversations->postFromVendor(
            $supportConversation,
            $request->user(),
            $request->string('body')->toString(),
            imageUrl: $request->validated('image_url'),
        );

        $message->load('sender:id,name,avatar');

        return $this->success(__('Reply sent successfully.'), [
            'message' => new SupportMessageResource($message),
            'conversation' => new SupportConversationResource($supportConversation->fresh()),
        ]);
    }

    public function markRead(Request $request, SupportConversation $supportConversation): JsonResponse
    {
        $this->guardVendorQuery($supportConversation, $request->user()->vendorProfile);

        $marked = $this->conversations->markReadFor($supportConversation, SupportSenderRole::Vendor);

        return $this->success(__('Conversation marked as read.'), [
            'marked' => $marked,
            'conversation' => new SupportConversationResource($supportConversation->fresh()),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendorProfile;

        return $this->success(__('Unread count fetched successfully.'), [
            'unread_count' => $this->conversations->productQueryUnreadCountForVendor($vendor),
        ]);
    }

    /**
     * Only the vendor whose product is being asked about may see the thread,
     * and only product-query threads belong to this inbox at all.
     */
    private function guardVendorQuery(SupportConversation $conversation, VendorProfile $vendor): void
    {
        if ($conversation->kind !== SupportConversationKind::ProductQuery
            || $conversation->vendor_id !== $vendor->id) {
            throw ValidationException::withMessages([
                'conversation' => __('This conversation does not belong to this inbox.'),
            ]);
        }
    }
}
