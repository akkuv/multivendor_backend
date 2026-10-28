<?php

namespace App\Notifications;

use App\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorSaleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public OrderItem $orderItem)
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
            ->subject(__('New Sale: :product', ['product' => $this->orderItem->product_name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('You have a new sale on your store.'))
            ->line(__('Product: :product', ['product' => $this->orderItem->product_name]))
            ->line(__('Quantity: :qty', ['qty' => $this->orderItem->quantity]))
            ->line(__('Order Total: Rs. :total', ['total' => number_format((float) $this->orderItem->total, 2)]))
            ->action(__('View Order'), url(config('app.frontend_url', 'http://localhost:5173').'/vendor/orders'))
            ->line(__('Thank you for selling with us!'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_sale',
            'title' => __('New sale received'),
            'message' => __('You have a new sale: :product (Qty: :qty)', [
                'product' => $this->orderItem->product_name,
                'qty' => $this->orderItem->quantity,
            ]),
            'order_item_id' => $this->orderItem->id,
        ];
    }
}
