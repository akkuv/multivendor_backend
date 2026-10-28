<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Enums\SupportSenderRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Support\SendSupportMessageRequest;
use App\Http\Resources\V1\SupportConversationResource;
use App\Http\Resources\V1\SupportMessageResource;
use App\Services\Support\SupportConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The customer's side of support chat.
 *
 * Mirrors the vendor controller exactly: one thread per customer, created on
 * first visit so the chat UI never has to handle a "no conversation yet" state.
 */
class SupportController extends Controller
{
    public function __construct(private readonly SupportConversationService $conversations) {}

    public function show(Request $request): JsonResponse
    {
        $conversation = $this->conversations->findOrCreateForCustomer($request->user());
        $conversation->load([
            'customerUser:id,name,email,phone,avatar',
            // The customer's recent orders, so the thread can show what they are
            // asking about right next to their first message.
            'customerOrders' => fn ($query) => $query->limit(3),
            'customerOrders.items',
        ]);

        $messages = $conversation->messages()
            ->with('sender:id,name,avatar')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 50));

        return $this->success(__('Support conversation fetched successfully.'), [
            'conversation' => new SupportConversationResource($conversation),
            'messages' => $this->paginatedPayload(
                $messages,
                fn ($items) => SupportMessageResource::collection($items),
            ),
            'suggestions' => $this->conversations->suggestionsFor($conversation),
        ]);
    }

    public function store(SendSupportMessageRequest $request): JsonResponse
    {
        $user = $request->user();
        $conversation = $this->conversations->findOrCreateForCustomer($user);

        ['message' => $message, 'bot_reply' => $botReply] = $this->conversations->postFromRequester(
            $conversation,
            $user,
            $request->string('body')->toString(),
            // `skip_bot` lets the customer go straight to a human on this message.
            askBot: ! $request->boolean('skip_bot', false),
        );

        $message->load('sender:id,name,avatar');
        $botReply?->load('sender:id,name,avatar');
        $conversation->load('customerUser:id,name,email,phone,avatar');

        return $this->success(__('Message sent successfully.'), [
            'message' => new SupportMessageResource($message),
            'bot_reply' => $botReply === null ? null : new SupportMessageResource($botReply),
            'conversation' => new SupportConversationResource($conversation->fresh()),
        ]);
    }

    /**
     * Mark everything the customer has now seen as read, so the admin inbox
     * reflects reality the next time they open it.
     */
    public function markRead(Request $request): JsonResponse
    {
        $conversation = $this->conversations->findOrCreateForCustomer($request->user());

        $marked = $this->conversations->markReadFor($conversation, SupportSenderRole::Customer);

        return $this->success(__('Conversation marked as read.'), [
            'marked' => $marked,
            'conversation' => new SupportConversationResource($conversation->fresh()),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $conversation = $this->conversations->findOrCreateForCustomer($request->user());

        return $this->success(__('Unread count fetched successfully.'), [
            'unread_count' => $this->conversations->unreadCountForRequester($conversation),
        ]);
    }
}
