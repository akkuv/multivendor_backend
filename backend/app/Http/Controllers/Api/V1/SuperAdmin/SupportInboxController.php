<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\SupportConversationKind;
use App\Enums\SupportRequesterType;
use App\Enums\SupportSenderRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Support\SendSupportMessageRequest;
use App\Http\Resources\V1\SupportConversationResource;
use App\Http\Resources\V1\SupportMessageResource;
use App\Models\SupportConversation;
use App\Services\Support\SupportConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The admin side of support chat, shared by the vendor and customer inboxes.
 *
 * Both inboxes are the same shape — one thread per requester, newest activity
 * first, with the threads needing a human first — so they differ only in which
 * requester type they are scoped to. Keeping one implementation means a fix to
 * reading, replying or closing a thread lands in both.
 */
abstract class SupportInboxController extends Controller
{
    public function __construct(protected readonly SupportConversationService $conversations) {}

    /**
     * Which side of the marketplace this inbox serves. Null means the inbox
     * spans both requester types (the product-query inbox), where the kind
     * check alone keeps the other inboxes out.
     */
    abstract protected function requesterType(): ?SupportRequesterType;

    /**
     * Which conversation kind this inbox serves. The everyday vendor and
     * customer inboxes serve `General`; the order-query inbox serves
     * `OrderQuery` so admin-initiated order threads keep their own queue.
     */
    protected function kind(): SupportConversationKind
    {
        return SupportConversationKind::General;
    }

    /**
     * The admin inbox: one row per requester, most recent activity first, with
     * the threads that need a human hoisted to the top.
     */
    public function index(Request $request): JsonResponse
    {
        $filter = $request->string('filter')->toString() ?: 'open';
        $type = $this->requesterType();

        $query = SupportConversation::query()
            ->forKind($this->kind())
            ->when($type !== null, fn ($q) => $q->forRequesterType($type))
            ->with($this->relations())
            ->withCount([
                'messages as unread_for_admin' => fn ($q) => $q->unreadFor(SupportSenderRole::Admin->value),
                'messages as unread_count' => fn ($q) => $q->whereNull('read_at'),
            ]);

        match ($filter) {
            'closed' => $query->where('is_closed', true),
            'all' => $query,
            'awaiting' => $query
                ->where('is_closed', false)
                ->whereNotNull('last_requester_reply_at')
                ->where(fn ($q) => $q->whereNull('last_admin_reply_at')
                    ->orWhereColumn('last_requester_reply_at', '>', 'last_admin_reply_at')),
            default => $query->where('is_closed', false),
        };

        $conversations = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return $this->paginated(
            'Support conversations fetched successfully.',
            $conversations,
            fn ($items) => SupportConversationResource::collection($items),
            ['counts' => $this->conversations->adminInboxCounts($type, $this->kind())],
        );
    }

    /**
     * Badge totals only, for the admin sidebar. Deliberately separate from the
     * inbox so a layout that only needs the numbers does not pull whole pages of
     * conversations every poll.
     */
    public function counts(): JsonResponse
    {
        return $this->success(__('Support counts fetched successfully.'), [
            'counts' => $this->conversations->adminInboxCounts($this->requesterType(), $this->kind()),
        ]);
    }

    public function show(Request $request, SupportConversation $supportConversation): JsonResponse
    {
        $this->guardRequesterType($supportConversation);

        $supportConversation->load($this->threadRelations($supportConversation));

        $messages = $supportConversation->messages()
            ->with('sender:id,name,avatar')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 50));

        // The customer support inbox shows the same quick-question chips as the
        // customer's own chat, so the admin knows what the customer was offered
        // (and can send the same prompt with one click). Other inboxes have no
        // assistant, and therefore no suggestions.
        $suggestions = $supportConversation->kind === SupportConversationKind::General
            && $supportConversation->requester_type === SupportRequesterType::Customer
                ? $this->conversations->suggestionsFor($supportConversation)
                : [];

        return $this->success(__('Support conversation fetched successfully.'), [
            'conversation' => new SupportConversationResource($supportConversation),
            'messages' => $this->paginatedPayload(
                $messages,
                fn ($items) => SupportMessageResource::collection($items),
            ),
            'suggestions' => $suggestions,
        ]);
    }

    public function store(SendSupportMessageRequest $request, SupportConversation $supportConversation): JsonResponse
    {
        $this->guardRequesterType($supportConversation);

        $message = $this->conversations->postFromAdmin(
            $supportConversation,
            $request->user(),
            $request->string('body')->toString(),
            imageUrl: $request->validated('image_url'),
        );

        $message->load('sender:id,name,avatar');

        // Opening the thread is what marks it read, matching every other inbox.
        $this->conversations->markReadFor($supportConversation, SupportSenderRole::Admin);

        return $this->success(__('Reply sent successfully.'), [
            'message' => new SupportMessageResource($message),
            'conversation' => new SupportConversationResource($supportConversation->fresh()),
        ]);
    }

    public function markRead(Request $request, SupportConversation $supportConversation): JsonResponse
    {
        $this->guardRequesterType($supportConversation);

        $marked = $this->conversations->markReadFor($supportConversation, SupportSenderRole::Admin);

        return $this->success(__('Conversation marked as read.'), [
            'marked' => $marked,
            'conversation' => new SupportConversationResource($supportConversation->fresh()),
        ]);
    }

    /**
     * Close a resolved thread. A requester writing again reopens it, so this is a
     * tidy-up rather than a lock-out.
     */
    public function close(Request $request, SupportConversation $supportConversation): JsonResponse
    {
        $this->guardRequesterType($supportConversation);

        $supportConversation->forceFill([
            'is_closed' => true,
            'closed_at' => now(),
        ])->save();

        $this->conversations->markReadFor($supportConversation, SupportSenderRole::Admin);

        return $this->success(__('Conversation closed.'), [
            'conversation' => new SupportConversationResource($supportConversation->fresh()),
        ]);
    }

    /**
     * Reopen a closed thread from the admin side.
     */
    public function reopen(Request $request, SupportConversation $supportConversation): JsonResponse
    {
        $this->guardRequesterType($supportConversation);

        if (! $supportConversation->is_closed) {
            throw ValidationException::withMessages([
                'conversation' => __('This conversation is already open.'),
            ]);
        }

        $supportConversation->forceFill([
            'is_closed' => false,
            'closed_at' => null,
        ])->save();

        return $this->success(__('Conversation reopened.'), [
            'conversation' => new SupportConversationResource($supportConversation->fresh()),
        ]);
    }

    /**
     * Eager loads for the inbox and the thread header.
     *
     * `latestMessage` is in here because the inbox preview is the newest message;
     * without it every row renders as "No messages yet".
     *
     * @return list<string>
     */
    protected function relations(): array
    {
        return ['latestMessage'];
    }

    /**
     * Eager loads for the open-thread view, which may pull heavier context on
     * top of what the inbox rows need.
     *
     * @return list<string>
     */
    protected function threadRelations(SupportConversation $conversation): array
    {
        return $this->relations();
    }

    /**
     * A conversation from the other inbox must not be reachable through this
     * one's URL, so the two inboxes stay genuinely separate. Inboxes without a
     * requester type are separated by conversation kind alone.
     */
    protected function guardRequesterType(SupportConversation $conversation): void
    {
        $type = $this->requesterType();

        if ($type !== null && $conversation->requester_type !== $type) {
            throw ValidationException::withMessages([
                'conversation' => __('This conversation does not belong to this inbox.'),
            ]);
        }

        if ($conversation->kind !== $this->kind()) {
            throw ValidationException::withMessages([
                'conversation' => __('This conversation does not belong to this inbox.'),
            ]);
        }
    }
}
