<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Enums\Role;
use App\Events\NotificationCreated;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\UserMailNotifiable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationDispatcher
{
    public function send(
        User $user,
        NotificationType $type,
        string $title,
        string $message,
        array $payload = [],
        bool $email = true,
    ): void {
        $this->deliverNow($user, new AppNotification($type->value, $title, $message, $payload, $email));
    }

    /**
     * Deliver a notification with the in-app (database) channel written synchronously so it
     * shows up in real time, while the email stays queued so a slow or broken mail transport
     * never blocks the request.
     */
    public function deliverNow(User $user, Notification $notification): void
    {
        $user->notifyNow($notification, ['database']);

        if ($notification instanceof AppNotification) {
            $this->broadcastCreated($user, $notification);
        }

        if (in_array('mail', $notification->via($user), true)) {
            (new UserMailNotifiable($user->email, $user->name))->notify($notification);
        }
    }

    /** @return Collection<int, User> */
    public function admins(): Collection
    {
        return User::query()->where('role', Role::SuperAdmin->value)->get();
    }

    public function notifyAdmins(
        NotificationType $type,
        string $title,
        string $message,
        array $payload = [],
        bool $email = false,
    ): void {
        $notification = new AppNotification($type->value, $title, $message, $payload, $email);

        foreach ($this->admins() as $admin) {
            $admin->notifyNow($notification);
            $this->broadcastCreated($admin, $notification);
        }
    }

    /**
     * Push the notification over the recipient's private channel so a signed-in
     * dashboard can show it without polling. The event carries the same data the
     * API resource exposes; the frontend refetches for the row id.
     */
    private function broadcastCreated(User $user, AppNotification $notification): void
    {
        // Real-time delivery is a progressive enhancement: the notification is
        // already persisted, so a stopped/restarted websocket server (Reverb)
        // must never fail the request that triggered it.
        try {
            broadcast(new NotificationCreated(
                $user->id,
                $notification->type,
                $notification->title,
                $notification->message,
                $notification->payload,
            ));
        } catch (Throwable $e) {
            Log::warning('Failed to broadcast notification.', [
                'user_id' => $user->id,
                'type' => $notification->type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
