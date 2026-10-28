<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ShipmentResource;
use App\Models\Order;
use App\Services\Shipping\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackController extends Controller
{
    public function __construct(protected ShippingService $shippingService) {}

    public function track(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return $this->error(__('Order not found.'), null, 404);
        }

        $shipment = $order->shipment;

        if (! $shipment || ! $shipment->tracking_number) {
            return $this->error(__('No shipment found for this order yet.'), null, 404);
        }

        // A courier lookup failure must not hide the shipment we already know
        // about, so the stored status and event history are always returned.
        try {
            $tracking = $this->shippingService->track($shipment);
            $notice = null;
        } catch (\Throwable $e) {
            report($e);

            $tracking = null;
            $notice = 'Live courier updates are temporarily unavailable. Showing the latest known status.';
        }

        return $this->success(__('Tracking information.'), [
            'shipment' => new ShipmentResource($shipment->load('events')),
            'tracking' => $tracking,
            'tracking_notice' => $notice,
        ]);
    }
}
