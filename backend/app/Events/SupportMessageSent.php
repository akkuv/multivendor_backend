<?php

namespace App\Events;

use App\Enums\SupportSenderRole;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast whenever a message lands in a requester <-> admin conversation.
 *
 * The channel is shared by both sides so the admin inbox and the vendor chat
 * window both hear the same event without needing to know who sent it.
 *
 * Broadcast inline rather than on the queue: a chat message that arrives a few
 * seconds late is not a chat, and it keeps the feature to a single extra
 * process (the Reverb server) instead of also requiring a queue worker.
 */
class SupportMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SupportConversation $conversation,
        public SupportMessage $message,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support.conversation.'.$this->conversation->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastAs(): string
    {
        return 'support.message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'requester_type' => $this->conversation->requester_type?->value,
            'message_id' => $this->message->id,
            'message' => [
                'id' => $this->message->id,
                'support_conversation_id' => $this->message->support_conversation_id,
                'sender_user_id' => $this->message->sender_user_id,
                'sender_role' => $this->message->sender_role,
                'sender_name' => $this->message->authorName(),
                'body' => $this->message->body,
                'is_bot' => (bool) $this->message->is_bot,
                'read_at' => $this->message->read_at?->toIso8601String(),
                'created_at' => $this->message->created_at?->toIso8601String(),
            ],
            'conversation' => [
                'id' => $this->conversation->id,
                'last_message_at' => $this->conversation->last_message_at?->toIso8601String(),
                'last_admin_reply_at' => $this->conversation->last_admin_reply_at?->toIso8601String(),
                'last_requester_reply_at' => $this->conversation->last_requester_reply_at?->toIso8601String(),
                'is_closed' => (bool) $this->conversation->is_closed,
                'unread_count' => $this->conversation->messages()->unreadFor($this->audience())->count(),
            ],
        ];
    }

    /**
     * The side that should see the new message as unread: the admin when the
     * requester spoke, the requester when staff did.
     */
    private function audience(): string
    {
        return $this->message->sender_role === SupportSenderRole::Admin->value
            ? $this->conversation->requesterRole()->value
            : SupportSenderRole::Admin->value;
    }
}
