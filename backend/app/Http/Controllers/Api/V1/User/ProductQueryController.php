<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Enums\SupportConversationKind;
use App\Enums\SupportRequesterType;
use App\Enums\SupportSenderRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Support\SendSupportMessageRequest;
use App\Http\Requests\Api\V1\Support\StoreProductQueryRequest;
use App\Http\Resources\V1\SupportConversationResource;
use App\Http\Resources\V1\SupportMessageResource;
use App\Models\Product;
use App\Models\SupportConversation;
use App\Services\Support\SupportConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The customer's side of product queries: private questions about a product
 * addressed to the vendor who sells it and the admin team together. Every ask
 * starts a brand new thread; there is no assistant, so every reply goes to a
 * person.
 */
class ProductQueryController extends Controller
{
    public function __construct(
        private readonly SupportConversationService $conversations,
    ) {}

    /**
     * Every product query this customer has opened, newest first, each with the
     * product it concerns and how many replies are still unread.
     */
    public function index(Request $request): JsonResponse
    {
        $conversations = SupportConversation::query()
            ->forKind(SupportConversationKind::ProductQuery)
            ->forRequesterType(SupportRequesterType::Customer)
            ->where('customer_user_id', $request->user()->id)
            ->with([
                'product:id,vendor_id,name,slug,status,images',
                'product.vendor:id,store_name,slug,logo,status',
                'latestMessage',
            ])
            ->withCount([
                'messages as unread_count' => fn ($query) => $query->unreadFor(SupportSenderRole::Customer->value),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return $this->paginated(
            __('Product queries fetched successfully.'),
            $conversations,
            fn ($items) => SupportConversationResource::collection($items),
            ['unread_count' => $this->conversations->customerProductQueryUnreadCountFor($request->user())],
        );
    }

    /**
     * Ask about a product. The thread is a fresh one every time, the product's
     * vendor is the first person told, and the admin team sees it in their
     * product-query inbox.
     */
    public function store(StoreProductQueryRequest $request): JsonResponse
    {
        $product = Product::query()->findOrFail($request->integer('product_id'));

        $conversation = $this->conversations->createProductQuery(
            $product,
            $request->user(),
            $request->string('body')->toString(),
            imageUrl: $request->validated('image_url'),
        );

        $message = $conversation->messages()->latest('id')->first();

        $conversation->load([
            'product:id,vendor_id,name,slug,status,images,price,mrp',
            'product.vendor:id,store_name,slug,logo,status',
            'customerOrders' => fn ($query) => $query
                ->whereHas('items', fn ($items) => $items->where('product_id', $conversation->product_id))
                ->with(['items:id,order_id,product_id,quantity,vendor_id,vendor_order_id']),
        ]);
        $message?->load('sender:id,name,avatar');

        return $this->success(__('Product query created successfully.'), [
            'conversation' => new SupportConversationResource($conversation),
            'message' => $message === null ? null : new SupportMessageResource($message),
        ], 201);
    }

    public function show(Request $request, SupportConversation $supportConversation): JsonResponse
    {
        $this->guardCustomerQuery($supportConversation, $request->user()->id);

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
     * The customer keeps the conversation going; still no assistant, the reply
     * goes to whoever read last.
     */
    public function reply(SendSupportMessageRequest $request, SupportConversation $supportConversation): JsonResponse
    {
        $this->guardCustomerQuery($supportConversation, $request->user()->id);

        ['message' => $message] = $this->conversations->postFromRequester(
            $supportConversation,
            $request->user(),
            $request->string('body')->toString(),
            askBot: false,
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
        $this->guardCustomerQuery($supportConversation, $request->user()->id);

        $marked = $this->conversations->markReadFor($supportConversation, SupportSenderRole::Customer);

        return $this->success(__('Conversation marked as read.'), [
            'marked' => $marked,
            'conversation' => new SupportConversationResource($supportConversation->fresh()),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success(__('Unread count fetched successfully.'), [
            'unread_count' => $this->conversations->customerProductQueryUnreadCountFor($request->user()),
        ]);
    }

    /**
     * Only the customer who opened the thread may see it, and only product-query
     * threads belong to this inbox at all.
     */
    private function guardCustomerQuery(SupportConversation $conversation, int $customerUserId): void
    {
        if ($conversation->kind !== SupportConversationKind::ProductQuery
            || $conversation->requester_type !== SupportRequesterType::Customer
            || (int) $conversation->customer_user_id !== $customerUserId) {
            throw ValidationException::withMessages([
                'conversation' => __('This conversation does not belong to this inbox.'),
            ]);
        }
    }
}
