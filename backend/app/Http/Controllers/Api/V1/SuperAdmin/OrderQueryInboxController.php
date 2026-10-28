<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\SupportConversationKind;
use App\Enums\SupportRequesterType;
use App\Http\Requests\Api\V1\Support\StoreOrderQueryRequest;
use App\Http\Resources\V1\SupportConversationResource;
use App\Http\Resources\V1\SupportMessageResource;
use App\Models\Order;
use App\Models\SupportConversation;
use App\Models\VendorProfile;
use App\Services\Support\SupportConversationService;
use Illuminate\Http\JsonResponse;

/**
 * The admin side of order queries: admin-initiated, order-linked threads in
 * which the marketplace team asks a vendor for order-related information.
 *
 * Shares the inbox machinery with the everyday vendor and customer support
 * inboxes, but serves only `OrderQuery` conversations.
 */
class OrderQueryInboxController extends SupportInboxController
{
    public function __construct(
        SupportConversationService $conversations,
    ) {
        parent::__construct($conversations);
    }

    protected function requesterType(): SupportRequesterType
    {
        return SupportRequesterType::Vendor;
    }

    protected function kind(): SupportConversationKind
    {
        return SupportConversationKind::OrderQuery;
    }

    /**
     * @return list<string>
     */
    protected function relations(): array
    {
        return [
            // The vendor is who the query is addressed to; without it the admin
            // inbox rows, thread header and composer all fall back to "Vendor".
            'vendor:id,store_name,slug,logo,status',
            'order:id,order_number,order_status,total,created_at,user_id,subtotal,discount,tax,shipping_charge,cod_charge,fees_total,wallet_amount',
            'order.vendorOrders:id,order_id,vendor_id',
            'order.user:id,name,email',
            ...parent::relations(),
        ];
    }

    /**
     * The open thread also loads the order's item lines, so both the admin and
     * the vendor see the full order details next to the first message.
     *
     * @return list<string>
     */
    protected function threadRelations(SupportConversation $conversation): array
    {
        return [
            ...$this->relations(),
            'order.items',
        ];
    }

    /**
     * Start a query about an order, or continue the existing thread for that
     * order and vendor if one is already open. The admin's message is recorded
     * and the vendor is told a query has arrived.
     */
    public function create(StoreOrderQueryRequest $request): JsonResponse
    {
        $order = Order::query()->findOrFail($request->integer('order_id'));
        $vendor = VendorProfile::query()->findOrFail($request->integer('vendor_id'));

        $conversation = $this->conversations->findOrCreateOrderQuery($order, $vendor);

        $message = $this->conversations->postFromAdmin(
            $conversation,
            $request->user(),
            $request->string('body')->toString(),
        );

        $message->load('sender:id,name,avatar');

        $conversation->load($this->relations());

        return $this->success(__('Order query created successfully.'), [
            'conversation' => new SupportConversationResource($conversation),
            'message' => new SupportMessageResource($message),
        ], 201);
    }
}
