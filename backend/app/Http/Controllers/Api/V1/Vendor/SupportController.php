<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Enums\SupportSenderRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Support\SendSupportMessageRequest;
use App\Http\Resources\V1\SupportConversationResource;
use App\Http\Resources\V1\SupportMessageResource;
use App\Services\Support\SupportConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function __construct(private readonly SupportConversationService $conversations) {}

    /**
     * The vendor's single support thread, created on first visit so the chat UI
     * never has to deal with a "no conversation yet" state.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $vendor = $user->vendorProfile;

        $conversation = $this->conversations->findOrCreateForVendor($vendor);
        $conversation->load(['vendor:id,store_name,slug,logo,status', 'vendorUser:id,name,email,phone']);

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
        $vendor = $user->vendorProfile;

        $conversation = $this->conversations->findOrCreateForVendor($vendor);

        ['message' => $message, 'bot_reply' => $botReply] = $this->conversations->postFromRequester(
            $conversation,
            $user,
            $request->string('body')->toString(),
            // `skip_bot` lets the vendor go straight to a human on this message.
            askBot: ! $request->boolean('skip_bot', false),
        );

        $message->load('sender:id,name,avatar');
        $botReply?->load('sender:id,name,avatar');

        return $this->success(__('Message sent successfully.'), [
            'message' => new SupportMessageResource($message),
            'bot_reply' => $botReply === null ? null : new SupportMessageResource($botReply),
            'conversation' => new SupportConversationResource($conversation->fresh()),
        ]);
    }

    /**
     * Mark everything the vendor has now seen as read, so the admin inbox
     * reflects reality the next time they open it.
     */
    public function markRead(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendorProfile;
        $conversation = $this->conversations->findOrCreateForVendor($vendor);

        $marked = $this->conversations->markReadFor($conversation, SupportSenderRole::Vendor);

        return $this->success(__('Conversation marked as read.'), [
            'marked' => $marked,
            'conversation' => new SupportConversationResource($conversation->fresh()),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendorProfile;

        return $this->success(__('Unread count fetched successfully.'), [
            'unread_count' => $this->conversations->vendorUnreadCount($vendor),
        ]);
    }
}
