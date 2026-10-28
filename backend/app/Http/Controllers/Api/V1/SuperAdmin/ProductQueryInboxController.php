<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\SupportConversationKind;
use App\Enums\SupportRequesterType;
use App\Http\Requests\Api\V1\Support\StoreProductQueryRequest;
use App\Http\Resources\V1\SupportConversationResource;
use App\Http\Resources\V1\SupportMessageResource;
use App\Models\Product;
use App\Models\SupportConversation;
use App\Services\Support\SupportConversationService;
use Illuminate\Http\JsonResponse;

/**
 * The admin side of product queries: product-linked threads about a product,
 * answered by the vendor who sells it and the marketplace team together.
 *
 * Customers and vendors both open these — the inbox serves either requester
 * type, unlike the everyday inboxes, where the kind check alone keeps every
 * other conversation out. Like order queries, the marketplace team can also
 * start a product query: it is addressed to the product's vendor.
 */
class ProductQueryInboxController extends SupportInboxController
{
    public function __construct(
        SupportConversationService $conversations,
    ) {
        parent::__construct($conversations);
    }

    protected function requesterType(): ?SupportRequesterType
    {
        return null;
    }

    protected function kind(): SupportConversationKind
    {
        return SupportConversationKind::ProductQuery;
    }

    /**
     * @return list<string>
     */
    protected function relations(): array
    {
        return [
            'product:id,vendor_id,name,slug,status,images,price,mrp',
            'product.vendor:id,store_name,slug,logo,status',
            'customerUser:id,name,email,phone,avatar',
            ...parent::relations(),
        ];
    }

    /**
     * The open thread also carries the asking customer's purchases of the
     * product, so the admin can see whether and how often it was bought.
     *
     * @return list<string>
     */
    protected function threadRelations(SupportConversation $conversation): array
    {
        return [
            ...$this->relations(),
            'customerOrders' => fn ($query) => $query
                ->whereHas('items', fn ($items) => $items->where('product_id', $conversation->product_id))
                ->with(['items:id,order_id,product_id,quantity,vendor_id,vendor_order_id']),
        ];
    }

    /**
     * Start a product query for the marketplace team about any product. The
     * product's vendor is the requester side, so it lands in the vendor's own
     * product-query list and is answered there, exactly like an order query.
     */
    public function create(StoreProductQueryRequest $request): JsonResponse
    {
        $product = Product::query()->findOrFail($request->integer('product_id'));

        $conversation = $this->conversations->createAdminProductQuery(
            $product,
            $request->user(),
            $request->string('body')->toString(),
            imageUrl: $request->validated('image_url'),
        );

        $message = $conversation->messages()->latest('id')->first();

        $conversation->load($this->relations());
        $message?->load('sender:id,name,avatar');

        return $this->success(__('Product query created successfully.'), [
            'conversation' => new SupportConversationResource($conversation),
            'message' => $message === null ? null : new SupportMessageResource($message),
        ], 201);
    }
}
