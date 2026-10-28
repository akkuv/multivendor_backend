<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendVerificationCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $code,
        public string $purpose = 'verification',
    ) {
        $this->locale = app()->getLocale();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->purpose === 'email'
            ? __('Verify your email address')
            : __('Your verification code');

        return (new MailMessage)
            ->subject($subject)
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Your verification code is:'))
            ->line('**'.$this->code.'**')
            ->line(__('This code will expire in 10 minutes.'))
            ->line(__('If you did not request this, you can safely ignore this email.'));
    }
}
