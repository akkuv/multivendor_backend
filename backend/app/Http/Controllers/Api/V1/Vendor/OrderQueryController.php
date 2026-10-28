<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Enums\SupportConversationKind;
use App\Enums\SupportRequesterType;
use App\Enums\SupportSenderRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Support\SendSupportMessageRequest;
use App\Http\Resources\V1\SupportConversationResource;
use App\Http\Resources\V1\SupportMessageResource;
use App\Models\SupportConversation;
use App\Models\VendorProfile;
use App\Services\Support\SupportConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The vendor side of order queries: admin-initiated, order-linked questions
 * about the vendor's fulfilment of a specific order. Unlike the everyday
 * support chat there is no assistant — every reply goes to a person.
 */
class OrderQueryController extends Controller
{
    public function __construct(private readonly SupportConversationService $conversations) {}

    /**
     * Every order query for this vendor, newest activity first, each with the
     * order it concerns and how many admin messages are still unread.
     */
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendorProfile;

        $conversations = SupportConversation::query()
            ->forKind(SupportConversationKind::OrderQuery)
            ->forRequesterType(SupportRequesterType::Vendor)
            ->where('vendor_id', $vendor->id)
            ->with([
                'order:id,order_number,order_status,total,created_at,user_id,subtotal,discount,tax,shipping_charge,cod_charge,fees_total,wallet_amount',
                'order.vendorOrders:id,order_id,vendor_id',
                'order.user:id,name,email',
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
            'Order queries fetched successfully.',
            $conversations,
            fn ($items) => SupportConversationResource::collection($items),
            ['unread_count' => $this->conversations->orderQueryUnreadCountForVendor($vendor)],
        );
    }

    public function show(Request $request, SupportConversation $supportConversation): JsonResponse
    {
        $this->guardVendorQuery($supportConversation, $request->user()->vendorProfile);

        $supportConversation->load([
            'order:id,order_number,order_status,total,created_at,user_id,subtotal,discount,tax,shipping_charge,cod_charge,fees_total,wallet_amount',
            'order.vendorOrders:id,order_id,vendor_id',
            'order.user:id,name,email',
            'vendor:id,store_name,slug,logo,status',
            // Item lines for the shared order-details card above the transcript.
            'order.items',
        ]);

        $messages = $supportConversation->messages()
            ->with('sender:id,name,avatar')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 50));

        return $this->success(__('Order query fetched successfully.'), [
            'conversation' => new SupportConversationResource($supportConversation),
            'messages' => $this->paginatedPayload(
                $messages,
                fn ($items) => SupportMessageResource::collection($items),
            ),
        ]);
    }

    /**
     * A vendor reply stays with the admin team: no assistant, no suggestions.
     */
    public function store(SendSupportMessageRequest $request, SupportConversation $supportConversation): JsonResponse
    {
        $this->guardVendorQuery($supportConversation, $request->user()->vendorProfile);

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
            'unread_count' => $this->conversations->orderQueryUnreadCountForVendor($vendor),
        ]);
    }

    /**
     * Only the vendor on the thread may see it, and only order-query threads
     * belong to this inbox at all.
     */
    private function guardVendorQuery(SupportConversation $conversation, VendorProfile $vendor): void
    {
        if ($conversation->kind !== SupportConversationKind::OrderQuery
            || $conversation->vendor_id !== $vendor->id) {
            throw ValidationException::withMessages([
                'conversation' => __('This conversation does not belong to this inbox.'),
            ]);
        }
    }
}
