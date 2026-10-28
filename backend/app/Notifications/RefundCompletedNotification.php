<?php

namespace App\Notifications;

use App\Models\Refund;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Refund $refund)
    {
        $this->locale = app()->getLocale();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Refund Processed'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Your refund has been processed successfully.'))
            ->line(__('Amount: Rs. :amount', ['amount' => number_format((float) $this->refund->amount, 2)]))
            ->line(__('Order: :order', ['order' => $this->refund->order?->order_number]))
            ->action(__('View Order'), url(config('app.frontend_url', 'http://localhost:5173').'/account/orders'))
            ->line(__('The amount will reflect in your account / wallet shortly.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'refund_completed',
            'title' => __('Refund processed'),
            'message' => __('Your refund of Rs. :amount has been processed.', ['amount' => $this->refund->amount]),
            'refund_id' => $this->refund->id,
        ];
    }
}
