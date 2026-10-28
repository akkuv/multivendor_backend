<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order)
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
            ->subject(__('Order Confirmed: :order', ['order' => $this->order->order_number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Your order has been placed successfully.'))
            ->line(__('Order Number: :order', ['order' => $this->order->order_number]))
            ->line(__('Total: Rs. :total', ['total' => number_format((float) $this->order->total, 2)]))
            ->action(__('View Order'), url(config('app.frontend_url', 'http://localhost:5173').'/account/orders'))
            ->line(__('Thank you for shopping with us!'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_placed',
            'title' => __('Order placed successfully'),
            'message' => __('Your order :order has been placed.', ['order' => $this->order->order_number]),
            'order_id' => $this->order->id,
            'total' => (float) $this->order->total,
        ];
    }
}
