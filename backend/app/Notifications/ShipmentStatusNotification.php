<?php

namespace App\Notifications;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Illuminate\Notifications\Notification;

class ShipmentStatusNotification extends Notification
{
    public function __construct(
        public Shipment $shipment,
        public ShipmentStatus $status,
    ) {
        $this->locale = app()->getLocale();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'shipment_status',
            'title' => __('Shipment :status', ['status' => $this->status->label()]),
            'message' => __('Your shipment :tracking is now :status.', [
                'tracking' => $this->shipment->tracking_number,
                'status' => $this->status->label(),
            ]),
            'order_id' => $this->shipment->order_id,
            'shipment_id' => $this->shipment->id,
            'status' => $this->status->value,
            'tracking_number' => $this->shipment->tracking_number,
            'tracking_url' => $this->shipment->tracking_url,
        ];
    }
}
