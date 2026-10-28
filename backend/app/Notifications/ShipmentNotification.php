<?php

namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShipmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Shipment $shipment)
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
            ->subject(__('Your order has been shipped'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Your order has been shipped.'))
            ->line(__('Tracking Number: :tracking', ['tracking' => $this->shipment->tracking_number ?? 'N/A']))
            ->line(__('Courier: :courier', ['courier' => $this->shipment->courier_name ?? $this->shipment->provider]))
            ->action(__('Track Order'), url(config('app.frontend_url', 'http://localhost:5173').'/account/orders'))
            ->line(__('Thank you for your patience!'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_shipped',
            'title' => __('Order shipped'),
            'message' => __('Your order has been shipped. Tracking: :tracking', ['tracking' => $this->shipment->tracking_number ?? 'N/A']),
            'order_id' => $this->shipment->order_id,
            'tracking_number' => $this->shipment->tracking_number,
        ];
    }
}
