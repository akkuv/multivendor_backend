<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Generic in-app + email notification used to keep channel content consistent.
 *
 * @param  array<string, mixed>  $payload
 */
class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $type,
        public string $title,
        public string $message,
        public array $payload = [],
        public bool $email = true,
    ) {
        $this->locale = app()->getLocale();
    }

    public function via(object $notifiable): array
    {
        return $this->email ? ['mail', 'database'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting($notifiable->name
                ? __('Hello :name,', ['name' => $notifiable->name])
                : __('Hello there,'))
            ->line($this->message)
            ->line(__('Thank you for using :app!', ['app' => config('app.name')]));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return array_merge([
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
        ], $this->payload);
    }
}
