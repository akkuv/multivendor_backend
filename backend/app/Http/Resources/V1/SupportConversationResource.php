<?php

namespace App\Http\Resources\V1;

use App\Enums\SupportConversationKind;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind?->value,
            'subject' => $this->subject,
            'requester_type' => $this->requester_type?->value,
            'vendor_id' => $this->vendor_id,
            'vendor_user_id' => $this->vendor_user_id,
            'customer_user_id' => $this->customer_user_id,
            'last_message_at' => $this->last_message_at,
            'last_admin_reply_at' => $this->last_admin_reply_at,
            'last_requester_reply_at' => $this->last_requester_reply_at,
            'is_closed' => (bool) $this->is_closed,
            'closed_at' => $this->closed_at,
            'is_waiting_on_admin' => $this->isWaitingOnAdmin(),
            'created_at' => $this->created_at,
            'unread_count' => $this->when(
                isset($this->unread_count),
                fn () => (int) $this->unread_count,
            ),
            'unread_for_admin' => $this->when(
                isset($this->unread_for_admin),
                fn () => (int) $this->unread_for_admin,
            ),
            'unread_for_vendor' => $this->when(
                isset($this->unread_for_vendor),
                fn () => (int) $this->unread_for_vendor,
            ),
            'order' => $this->whenLoaded('order', fn () => $this->order
                ? [
                    'id' => $this->order->id,
                    'order_number' => $this->order->order_number,
                    'order_status' => $this->order->order_status,
                    'total' => $this->order->total,
                    'created_at' => $this->order->created_at,
                    // The vendor-fulfilled order row the admin and vendor order
                    // detail links point at; null when that pair has none (and
                    // then the frontend renders the order number as plain text).
                    'vendor_order_id' => $this->order->relationLoaded('vendorOrders')
                        ? ($this->order->vendorOrders->firstWhere('vendor_id', $this->vendor_id)?->id ?? null)
                        : null,
                    'customer' => $this->order->relationLoaded('user') && $this->order->user
                        ? [
                            'id' => $this->order->user->id,
                            'name' => $this->order->user->name,
                            'email' => $this->order->user->email,
                        ]
                        : null,
                    // Order context for the shared thread card on order-query
                    // threads: item lines plus the price breakdown, present only
                    // when the thread payload loaded them (kept off the inbox
                    // rows so the list stays light).
                    'items' => $this->order->relationLoaded('items')
                        ? $this->order->items
                            ->map(fn (OrderItem $item) => [
                                'id' => $item->id,
                                'product_name' => $item->product_name,
                                'product_image' => $item->product_image,
                                'quantity' => (int) $item->quantity,
                                'unit_price' => (float) $item->unit_price,
                                'total' => (float) $item->total,
                            ])
                            ->values()
                        : null,
                    'subtotal' => (float) $this->order->subtotal,
                    'discount' => (float) $this->order->discount,
                    'tax' => (float) $this->order->tax,
                    'shipping_charge' => (float) $this->order->shipping_charge,
                    'cod_charge' => (float) ($this->order->cod_charge ?? 0),
                    'fees_total' => (float) ($this->order->fees_total ?? 0),
                    'wallet_amount' => (float) ($this->order->wallet_amount ?? 0),
                ]
                : null),
            'product' => $this->whenLoaded('product', fn () => $this->product
                ? [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'slug' => $this->product->slug,
                    'status' => $this->product->status,
                    'images' => $this->product->images ?? [],
                    'price' => (float) $this->product->price,
                    'mrp' => $this->product->mrp === null ? null : (float) $this->product->mrp,
                    'vendor' => $this->product->relationLoaded('vendor') && $this->product->vendor
                        ? [
                            'id' => $this->product->vendor->id,
                            'store_name' => $this->product->vendor->store_name,
                            'slug' => $this->product->vendor->slug,
                            'logo' => $this->product->vendor->logo,
                            'status' => $this->product->vendor->status,
                        ]
                        : null,
                ]
                : null),
            'product_purchases' => $this->when(
                $this->relationLoaded('customerOrders'),
                fn () => $this->customerOrders
                    ->map(fn (Order $order) => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'order_status' => $order->order_status,
                        'total' => $order->total,
                        'created_at' => $order->created_at,
                        'quantity' => (int) $order->items
                            ->where('product_id', $this->product_id)
                            ->sum('quantity'),
                        'vendor_order_id' => $order->items
                            ->where('product_id', $this->product_id)
                            ->where('vendor_id', $this->vendor_id)
                            ->first()?->vendor_order_id,
                    ])
                    ->values(),
            ),
            // The customer's recent orders, shown as context next to the first
            // message of their support chat so they (and the admin reading the
            // same thread) can see what they are asking about without leaving
            // the conversation. Loaded latest-first with a small limit.
            'recent_orders' => $this->when(
                $this->kind === SupportConversationKind::General
                    && $this->relationLoaded('customerOrders'),
                fn () => $this->customerOrders
                    ->map(fn (Order $order) => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'order_status' => $order->order_status,
                        'total' => (float) $order->total,
                        'created_at' => $order->created_at,
                        'items' => $order->relationLoaded('items')
                            ? $order->items
                                ->map(fn (OrderItem $item) => [
                                    'product_name' => $item->product_name,
                                    'quantity' => (int) $item->quantity,
                                ])
                                ->values()
                            : null,
                    ])
                    ->values(),
            ),
            'vendor' => $this->whenLoaded('vendor', fn () => [
                'id' => $this->vendor?->id,
                'store_name' => $this->vendor?->store_name,
                'slug' => $this->vendor?->slug,
                'logo' => $this->vendor?->logo,
                'status' => $this->vendor?->status,
            ]),
            'vendor_user' => $this->whenLoaded('vendorUser', fn () => [
                'id' => $this->vendorUser?->id,
                'name' => $this->vendorUser?->name,
                'email' => $this->vendorUser?->email,
                'phone' => $this->vendorUser?->phone,
            ]),
            'customer' => $this->whenLoaded('customerUser', fn () => $this->customerUser
                ? [
                    'id' => $this->customerUser->id,
                    'name' => $this->customerUser->name,
                    'email' => $this->customerUser->email,
                    'phone' => $this->customerUser->phone,
                    'avatar' => $this->customerUser->avatar,
                ]
                : null),
            'latest_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage
                ? [
                    'id' => $this->latestMessage->id,
                    'sender_role' => $this->latestMessage->sender_role,
                    'sender_name' => $this->latestMessage->authorName(),
                    'body' => $this->latestMessage->body,
                    'is_bot' => (bool) $this->latestMessage->is_bot,
                    'created_at' => $this->latestMessage->created_at,
                ]
                : null),
        ];
    }
}
