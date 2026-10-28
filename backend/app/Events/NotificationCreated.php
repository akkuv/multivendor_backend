<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast a freshly-created in-app notification to the recipient's own private
 * channel, so a signed-in dashboard can pop it up without polling.
 *
 * Delivered inline (ShouldBroadcastNow) rather than on the queue: a notification
 * that arrives a few seconds late defeats the point of a realtime bell, and it
 * keeps the feature to a single extra process (the Reverb server) — the same
 * trade-off the support-chat message event makes.
 */
class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $userId,
        public string $type,
        public string $title,
        public string $message,
        public array $payload = [],
    ) {}

    /**
     * The channel is the recipient's own user channel (authorised in
     * routes/channels.php), so a notification can never leak to another user.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * Mirror the shape the NotificationController resource exposes: type/title/
     * message split out, everything else in meta.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'meta' => collect($this->payload)->except(['type', 'title', 'message'])->all(),
        ];
    }
}
