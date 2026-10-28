<?php

namespace App\Services\Shipping;

use App\Enums\ShipmentPickupStatus;
use App\Enums\ShipmentStatus;
use App\Enums\VendorOrderStatus;
use App\Events\OrderShipped;
use App\Exceptions\ShiprocketApiException;
use App\Models\OrderAddress;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\ShipmentWebhook;
use App\Models\VendorOrder;
use App\Notifications\ShipmentStatusNotification;
use App\Services\Order\OrderStatusService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class ShippingService
{
    public function __construct(
        protected ShiprocketService $shiprocket,
        protected ShiprocketSettingsService $settings,
        protected OrderStatusService $orderStatus,
    ) {}

    /**
     * Push a vendor order through the full Shiprocket pipeline:
     * create order -> assign a courier -> generate AWB -> label.
     * The pickup request is a separate explicit step.
     *
     * When $courierId is supplied the courier chosen during rate shopping is
     * used; otherwise Shiprocket's recommendation wins.
     */
    public function ship(VendorOrder $vendorOrder, float $weight = 1.0, ?int $courierId = null): Shipment
    {
        $vendorOrder->load(['vendor', 'order.shippingAddress', 'order.orderAddress', 'order.user', 'items.product']);

        if ($this->usesLocalDriver()) {
            return $this->shipLocally($vendorOrder, $weight);
        }

        if (! $this->settings->enabled()) {
            throw new ShiprocketApiException(__('Shipping is not configured yet.'), 'shipment.create', retryable: false);
        }

        $shipment = Shipment::query()->firstOrNew(['vendor_order_id' => $vendorOrder->id]);

        try {
            $created = $this->shiprocket->createOrder($this->buildPayload($vendorOrder, $weight));

            $shipmentId = (int) (Arr::first((array) ($created['shipment_id'] ?? [])) ?? $created['shipment_id'] ?? 0);
            $shiprocketOrderId = (int) ($created['order_id'] ?? 0);

            $payload = $this->assignCourier($shiprocketOrderId, $shipmentId, $vendorOrder, $weight, $courierId);

            $awbCode = (string) ($payload['awb_code'] ?? ($payload['awb'] ?? ''));

            $label = $shipmentId ? $this->fetchLabel($shipmentId) : [];

            $shipment->fill([
                'order_id' => $vendorOrder->order_id,
                'provider' => 'Shiprocket',
                'shiprocket_order_id' => $shiprocketOrderId ?: null,
                'shipment_id' => $shipmentId ?: null,
                'tracking_number' => $awbCode ?: null,
                'tracking_url' => $awbCode ? 'https://shiprocket.co/tracking/'.$awbCode : null,
                'courier_id' => $this->courierId($payload) ?: null,
                'courier_name' => (string) ($payload['courier_name'] ?? $this->courierName($payload)),
                'pickup_location' => $this->pickupLocationFor($vendorOrder),
                'label_url' => $label['label_url'] ?? null,
                'pickup_status' => ShipmentPickupStatus::None->value,
                'status' => ShipmentStatus::AwbGenerated->value,
                'external_status' => 'NEW',
                'charges' => (float) ($created['cost_to_pay'] ?? $created['payment_details']['total_charges'] ?? 0),
                'weight' => $weight,
                'payload' => $created,
                'error_message' => null,
                'shipped_at' => now(),
            ])->save();

            $this->recordEvent($shipment, ShipmentStatus::Created, 'Shipment created on Shiprocket', isWebhook: false);
            $this->recordEvent($shipment, ShipmentStatus::AwbGenerated, "AWB {$awbCode} generated.", isWebhook: false);

            $this->orderStatus->changeVendorOrderStatus($vendorOrder, VendorOrderStatus::Shipped);

            OrderShipped::dispatch($shipment);

            return $shipment->fresh(['vendorOrder', 'order']);
        } catch (Throwable $e) {
            $this->markFailed($shipment, $vendorOrder, $e);

            throw $e;
        }
    }

    /**
     * Create a shipment without contacting Shiprocket. Used in local/dev
     * environments so the vendor fulfilment flow is fully exercisable.
     */
    protected function shipLocally(VendorOrder $vendorOrder, float $weight): Shipment
    {
        $shipment = Shipment::query()->firstOrNew(['vendor_order_id' => $vendorOrder->id]);

        $trackingNumber = 'LOCAL-'.strtoupper(Str::random(10));

        try {
            $shipment->fill([
                'order_id' => $vendorOrder->order_id,
                'provider' => 'Local',
                'shiprocket_order_id' => null,
                'shipment_id' => null,
                'tracking_number' => $trackingNumber,
                'tracking_url' => null,
                'courier_id' => null,
                'courier_name' => 'Local Delivery',
                'pickup_location' => $this->pickupLocationFor($vendorOrder),
                'label_url' => null,
                'pickup_status' => ShipmentPickupStatus::None->value,
                'status' => ShipmentStatus::AwbGenerated->value,
                'external_status' => 'NEW',
                'charges' => 0,
                'weight' => $weight,
                'payload' => ['local' => true, 'tracking_number' => $trackingNumber],
                'error_message' => null,
                'shipped_at' => now(),
            ])->save();

            $this->recordEvent($shipment, ShipmentStatus::Created, 'Shipment created (local mode).', isWebhook: false);
            $this->recordEvent($shipment, ShipmentStatus::AwbGenerated, "AWB {$trackingNumber} generated (local mode).", isWebhook: false);

            $this->orderStatus->changeVendorOrderStatus($vendorOrder, VendorOrderStatus::Shipped);

            OrderShipped::dispatch($shipment);

            return $shipment->fresh(['vendorOrder', 'order']);
        } catch (Throwable $e) {
            $this->markFailed($shipment, $vendorOrder, $e);

            throw $e;
        }
    }

    protected function markFailed(Shipment $shipment, VendorOrder $vendorOrder, Throwable $e): void
    {
        $shipment->forceFill([
            'order_id' => $vendorOrder->order_id,
            'status' => ShipmentStatus::Failed->value,
            'error_message' => $e->getMessage(),
            'pickup_status' => ShipmentPickupStatus::Failed->value,
        ])->save();

        $this->recordEvent($shipment, ShipmentStatus::Failed, $e->getMessage(), isWebhook: false);
    }

    protected function usesLocalDriver(): bool
    {
        return config('services.shiprocket.driver') === 'local';
    }

    /**
     * Whether real courier rates can be quoted for an order right now.
     */
    public function isRateShoppable(): bool
    {
        return ! $this->usesLocalDriver() && $this->settings->enabled();
    }

    /**
     * Re-attempt a failed shipment against a fresh Shiprocket order.
     */
    public function retry(VendorOrder $vendorOrder, Shipment $shipment, float $weight = 1.0): Shipment
    {
        $shipment->forceFill([
            'retry_count' => (int) $shipment->retry_count + 1,
            'error_message' => null,
        ])->save();

        return $this->ship($vendorOrder, $weight);
    }

    /**
     * Explicitly schedule a pickup for an AWB-generated shipment.
     */
    public function requestPickup(Shipment $shipment, ?string $date = null, ?string $time = null): Shipment
    {
        if ($this->usesLocalDriver()) {
            $shipment->forceFill([
                'pickup_status' => ShipmentPickupStatus::Scheduled->value,
                'pickup_requested_at' => now(),
            ])->save();

            $this->recordEvent($shipment, ShipmentStatus::PickupScheduled, 'Pickup scheduled (local mode).', isWebhook: false);

            return $shipment->fresh(['vendorOrder', 'order']);
        }

        if (! $shipment->shipment_id) {
            throw new \InvalidArgumentException('Shipment has not been created on Shiprocket yet.');
        }

        try {
            $response = $this->shiprocket->requestPickup([
                'shipment_id' => $shipment->shipment_id,
                'pickup_date' => $date ?: now()->addDay()->format('Y-m-d'),
                'pickup_time' => $time ?: '10:00',
            ]);
        } catch (ShiprocketApiException $e) {
            $shipment->forceFill(['pickup_status' => ShipmentPickupStatus::Failed->value])->save();

            throw $e;
        }

        $shipment->forceFill([
            'pickup_status' => ShipmentPickupStatus::Scheduled->value,
            'pickup_requested_at' => now(),
            'picker' => $this->pickerFromResponse($response, $shipment),
        ])->save();

        $this->recordEvent($shipment, ShipmentStatus::PickupScheduled, 'Pickup scheduled with courier.', isWebhook: false);

        return $shipment->fresh(['vendorOrder', 'order']);
    }

    /**
     * Shiprocket echoes the assigned field agent back on the pickup response;
     * persist it so operations can see who is collecting the parcel.
     *
     * @param  array<string, mixed>  $response
     */
    protected function pickerFromResponse(array $response, Shipment $shipment): ?string
    {
        foreach ($response['data'] ?? [] as $entry) {
            if (! is_array($entry) || (int) ($entry['shipment_id'] ?? 0) !== (int) $shipment->shipment_id) {
                continue;
            }

            foreach (['picker_name', 'name', 'picker'] as $key) {
                if (filled($entry[$key] ?? null)) {
                    return (string) $entry[$key];
                }
            }
        }

        return $shipment->picker;
    }

    /**
     * Couriers that can service a vendor order, with their rates, so a vendor
     * can compare prices before committing to a courier.
     *
     * @return array{available: bool, message: ?string, recommended_courier_id: ?int, couriers: array<int, array<string, mixed>>}
     */
    public function courierRates(VendorOrder $vendorOrder, float $weight = 1.0): array
    {
        if ($this->usesLocalDriver()) {
            return [
                'available' => true,
                'message' => null,
                'recommended_courier_id' => null,
                'couriers' => [[
                    'courier_id' => null,
                    'courier_name' => 'Local Delivery',
                    'rate' => 0.0,
                    'cod_available' => true,
                    'days' => null,
                    'is_recommended' => true,
                ]],
            ];
        }

        if (! $this->settings->enabled()) {
            throw new ShiprocketApiException(__('Shipping is not configured yet.'), 'courier.serviceability', retryable: false);
        }

        $address = $vendorOrder->order?->orderAddress ?? $vendorOrder->order?->shippingAddress;

        if (blank($address?->pincode)) {
            throw new \InvalidArgumentException('The delivery address has no pincode, so courier rates cannot be fetched.');
        }

        $response = $this->shiprocket->serviceabilityCheck([
            'pickup_postcode' => $vendorOrder->vendor?->pincode ?: '110001',
            'delivery_postcode' => $address->pincode,
            'cod' => strtoupper((string) $vendorOrder->order?->payment_method) === 'COD' ? 1 : 0,
            'weight' => $weight,
            'dimensions' => [
                'length' => 10,
                'breadth' => 10,
                'height' => 10,
            ],
        ]);

        $available = (array) ($response['data']['available_courier_companies'] ?? $response['available_courier_companies'] ?? []);
        $recommendedId = (int) ($response['recommended_courier']['courier_id'] ?? $response['data']['recommended_courier']['courier_id'] ?? 0);

        $couriers = collect($available)
            ->filter(fn (mixed $courier): bool => is_array($courier))
            ->map(fn (array $courier): array => [
                'courier_id' => (int) ($courier['courier_id'] ?? 0) ?: null,
                'courier_name' => (string) ($courier['courier_name'] ?? 'Unknown courier'),
                'rate' => round((float) ($courier['rate'] ?? 0), 2),
                'cod_available' => (bool) ($courier['cod_available'] ?? false),
                'days' => isset($courier['delivery_time']) ? (int) $courier['delivery_time'] : null,
                'is_recommended' => $recommendedId > 0
                    ? (int) ($courier['courier_id'] ?? 0) === $recommendedId
                    : false,
            ])
            ->sortBy('rate')
            ->values()
            ->all();

        if ($couriers === [] && $recommendedId === 0) {
            return [
                'available' => false,
                'message' => (string) ($response['message'] ?? 'No courier is able to service this pincode combination.'),
                'recommended_courier_id' => null,
                'couriers' => [],
            ];
        }

        return [
            'available' => true,
            'message' => null,
            'recommended_courier_id' => $recommendedId ?: ($couriers[0]['courier_id'] ?? null),
            'couriers' => $couriers,
        ];
    }

    /**
     * Resolve a printable shipping label URL, asking Shiprocket for one when it
     * has not been generated yet.
     *
     * @throws \InvalidArgumentException when no label can be produced, so callers
     *                                   never report an empty success to the UI.
     */
    public function label(Shipment $shipment): string
    {
        if (filled($shipment->label_url)) {
            return $shipment->label_url;
        }

        if ($this->usesLocalDriver()) {
            throw new \InvalidArgumentException('Shipping is running in local mode, so no label can be generated.');
        }

        if (! $shipment->shipment_id) {
            throw new \InvalidArgumentException('This shipment has not been created on Shiprocket yet, so it has no label.');
        }

        $label = $this->fetchLabel((int) $shipment->shipment_id);

        $url = $label['label_url'] ?? null;

        if (blank($url)) {
            throw new ShiprocketApiException(__('Shiprocket did not return a label for this shipment.'), 'shipments.print.label');
        }

        $shipment->forceFill(['label_url' => $url])->save();

        return $url;
    }

    /**
     * Generate a pickup manifest for a set of shipments. The resulting URL is
     * cached on each shipment so it can be re-downloaded later.
     *
     * @param  array<int, int>  $shipmentIds
     *
     * @throws \InvalidArgumentException when no manifest can be produced
     */
    public function manifest(array $shipmentIds): string
    {
        $ids = array_values(array_filter($shipmentIds));

        if ($ids === []) {
            throw new \InvalidArgumentException('Select at least one shipment to build a manifest.');
        }

        if ($this->usesLocalDriver()) {
            throw new \InvalidArgumentException('Shipping is running in local mode, so no pickup manifest can be generated.');
        }

        $externalIds = Shipment::query()
            ->whereIn('id', $ids)
            ->whereNotNull('shipment_id')
            ->pluck('shipment_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($externalIds === []) {
            throw new \InvalidArgumentException('None of the selected shipments exist on Shiprocket yet.');
        }

        $response = $this->shiprocket->generateManifest($externalIds);

        $url = $response['manifest_url'] ?? null;

        if (blank($url)) {
            throw new ShiprocketApiException(__('Shiprocket did not return a manifest for the selected shipments.'), 'shipments.print.manifest');
        }

        Shipment::query()->whereIn('id', $ids)->update(['manifest_url' => $url]);

        return $url;
    }

    /**
     * Cancel a shipment that has not yet been delivered.
     */
    public function cancel(Shipment $shipment): Shipment
    {
        if (in_array($shipment->status, [ShipmentStatus::Delivered, ShipmentStatus::Cancelled], true)) {
            throw new \InvalidArgumentException('This shipment can no longer be cancelled.');
        }

        if (! $this->usesLocalDriver()) {
            if (blank($shipment->shipment_id)) {
                throw new \InvalidArgumentException('Shipment has not been created on Shiprocket yet.');
            }

            try {
                $this->shiprocket->cancel([(int) $shipment->shipment_id]);
            } catch (ShiprocketApiException $e) {
                $shipment->forceFill([
                    'status' => ShipmentStatus::Failed->value,
                    'error_message' => $e->getMessage(),
                ])->save();

                throw $e;
            }
        }

        $shipment->forceFill([
            'status' => ShipmentStatus::Cancelled->value,
            'external_status' => 'CANCELLED',
            'error_message' => null,
        ])->save();

        $this->recordEvent($shipment, ShipmentStatus::Cancelled, 'Shipment cancelled.', isWebhook: false);

        return $shipment->fresh(['vendorOrder', 'order', 'events']);
    }

    public function track(Shipment $shipment): array
    {
        if (! $shipment->tracking_number) {
            throw new \InvalidArgumentException('Shipment has no tracking number.');
        }

        try {
            return $this->shiprocket->trackByAwb($shipment->tracking_number);
        } catch (ShiprocketApiException $e) {
            if (blank($shipment->shiprocket_order_id)) {
                throw $e;
            }

            // Some couriers only report against the Shiprocket order rather
            // than the AWB, so fall back to the coarser order-level lookup.
            return $this->shiprocket->trackByOrder((string) $shipment->shiprocket_order_id);
        }
    }

    /**
     * Validate + persist a Shiprocket webhook, updating shipment, vendor
     * order, master order and notifying the customer where applicable.
     */
    public function handleWebhook(string $rawPayload, string $signature, ?string $sourceIp = null): ShipmentWebhook
    {
        if (! $this->shiprocket->verifyWebhookSignature($rawPayload, $signature)) {
            throw new \InvalidArgumentException('Invalid webhook signature.');
        }

        $event = json_decode($rawPayload, true);

        if (! is_array($event)) {
            throw new \InvalidArgumentException('Invalid webhook payload.');
        }

        $webhook = ShipmentWebhook::create([
            'awb' => $this->webhookAwb($event),
            'event' => (string) ($event['current_status'] ?? $event['status'] ?? 'UNKNOWN'),
            'payload' => $event,
            'source_ip' => $sourceIp,
            'verified' => true,
            'status' => 'RECEIVED',
        ]);

        try {
            $status = $this->processWebhookEvent($webhook, $event);
            $webhook->forceFill(['status' => $status, 'processed_at' => now()])->save();

            return $webhook;
        } catch (Throwable $e) {
            logger()->error('Shiprocket webhook processing failed', [
                'message' => $e->getMessage(),
            ]);

            $webhook->forceFill(['status' => 'FAILED', 'processed_at' => now()])->save();

            throw $e;
        }
    }

    protected function processWebhookEvent(ShipmentWebhook $webhook, array $event): string
    {
        $shipment = $this->findShipment($event);

        if (! $shipment) {
            return 'IGNORED';
        }

        $webhook->forceFill(['shipment_id' => $shipment->id])->save();

        $external = (string) ($event['current_status'] ?? $event['status'] ?? $event['event'] ?? '');
        $status = $this->statusFromExternal($external) ?? ShipmentStatus::InTransit;

        $trackingData = $event['tracking_data'] ?? [];
        $last = is_array($trackingData) && $trackingData !== [] ? (array) end($trackingData) : [];
        $occurredAt = ! empty($last['time']) ? Carbon::parse($last['time']) : now()->subSecond(1);
        $description = (string) ($last['status'] ?? $external);

        $this->recordEvent($shipment, $status, $description, isWebhook: true, payload: $event, occurredAt: $occurredAt);

        $previous = $shipment->status instanceof ShipmentStatus
            ? $shipment->status
            : ShipmentStatus::tryFrom((string) $shipment->status) ?? ShipmentStatus::Pending;

        $updates = [
            'status' => $status->value,
            'last_event' => $event['unified_tracking'] ?? ($last['status'] ?? $external),
            'external_status' => $external,
        ];

        if ($status === ShipmentStatus::Delivered) {
            $updates['delivered_at'] = $shipment->delivered_at ?? $occurredAt;
        }

        if ($status === ShipmentStatus::PickedUp) {
            $updates['pickup_status'] = ShipmentPickupStatus::PickedUp->value;
        }

        $shipment->forceFill($updates)->save();

        if ($status->notifyCustomer() && $status !== $previous) {
            $this->notifyCustomer($shipment, $status);
        }

        if ($status === ShipmentStatus::Delivered && $shipment->vendorOrder) {
            $this->orderStatus->changeVendorOrderStatus($shipment->vendorOrder, VendorOrderStatus::Delivered);
        }

        return 'PROCESSED';
    }

    protected function notifyCustomer(Shipment $shipment, ShipmentStatus $status): void
    {
        $customer = $shipment->order?->user;

        if (! $customer) {
            return;
        }

        $customer->notify(new ShipmentStatusNotification($shipment, $status));
    }

    protected function findShipment(array $event): ?Shipment
    {
        $byId = (int) ($event['shipment_id'] ?? 0);

        if ($byId > 0) {
            $shipment = Shipment::query()->where('shipment_id', $byId)->first();

            if ($shipment) {
                return $shipment->load(['order.user', 'vendorOrder']);
            }
        }

        $awb = $this->webhookAwb($event);

        if ($awb) {
            return Shipment::query()->where('tracking_number', $awb)->with(['order.user', 'vendorOrder'])->first();
        }

        return null;
    }

    protected function webhookAwb(array $event): ?string
    {
        foreach (['awb', 'awb_code', 'courier_awb', 'tracking_number'] as $key) {
            $value = (string) ($event[$key] ?? '');

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function assignCourier(int $shiprocketOrderId, int $shipmentId, VendorOrder $vendorOrder, float $weight, ?int $preferredCourierId = null): array
    {
        if ($shipmentId <= 0) {
            return [];
        }

        $courierId = $preferredCourierId ?: $this->recommendCourier($shipmentId, $vendorOrder, $weight);

        try {
            return $this->shiprocket->generateAwb([
                'shipment_id' => $shipmentId,
                'courier_id' => $courierId ?: '',
            ]);
        } catch (ShiprocketApiException) {
            return $this->shiprocket->assignAwb([
                'shipment_id' => $shipmentId,
                'courier_id' => $courierId ?: '',
            ]);
        }
    }

    protected function recommendCourier(int $shipmentId, VendorOrder $vendorOrder, float $weight): int
    {
        $address = $vendorOrder->order->orderAddress ?? $vendorOrder->order->shippingAddress;

        try {
            $response = $this->shiprocket->serviceabilityCheck([
                'pickup_postcode' => $vendorOrder->vendor?->pincode ?? '110001',
                'delivery_postcode' => $address?->pincode ?? '',
                'cod' => $vendorOrder->order->payment_method === 'COD' ? 1 : 0,
                'weight' => $weight,
            ]);
        } catch (ShiprocketApiException) {
            return 0;
        }

        $recommended = $response['recommended_courier'] ?? null;

        if (! is_array($recommended)) {
            $available = $response['data']['available_courier_companies'] ?? ($response['available_courier_companies'] ?? []);

            if (is_array($available) && $available !== []) {
                return (int) (Arr::first($available)['courier_id'] ?? 0);
            }

            return 0;
        }

        return (int) ($recommended['courier_id'] ?? 0);
    }

    /** @return array<string, mixed> */
    protected function fetchLabel(int $shipmentId): array
    {
        try {
            return $this->shiprocket->generateLabel($shipmentId);
        } catch (ShiprocketApiException) {
            return [];
        }
    }

    protected function courierId(array $payload): int
    {
        return (int) ($payload['courier_id'] ?? 0);
    }

    protected function courierName(array $payload): string
    {
        return (string) ($payload['courier_name'] ?? Arr::first($payload['courier'] ?? [], fn ($courier): bool => true)['name'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(VendorOrder $vendorOrder, float $weight): array
    {
        $order = $vendorOrder->order;
        $address = $order->orderAddress ?? $order->shippingAddress;
        $customer = $order->user;

        return [
            'order_id' => $vendorOrder->vendor_order_number,
            'order_date' => $order->created_at->format('Y-m-d H:i'),
            'pickup_location' => $this->pickupLocationFor($vendorOrder),
            'billing_customer_name' => $address?->name ?? $customer->name,
            'billing_last_name' => '',
            'billing_address' => $this->addressLine($address),
            'billing_city' => $address?->city ?? '',
            'billing_pincode' => $address?->pincode ?? '',
            'billing_state' => $address?->state ?? '',
            'billing_country' => $address?->country ?? 'India',
            'billing_email' => $customer->email,
            'billing_phone' => ($address?->phone ?? $customer->phone) ?: '0000000000',
            'shipping_is_billing' => true,
            'order_items' => $vendorOrder->items->map(fn ($item) => [
                'name' => $item->product_name,
                'sku' => $item->product?->sku ?? (string) $item->product_id,
                'units' => $item->quantity,
                'selling_price' => (string) $item->unit_price,
            ])->values(),
            'payment_method' => strtoupper($order->payment_method) === 'COD' ? 'COD' : 'Prepaid',
            'shipping_charges' => (string) $vendorOrder->shipping_charge,
            'giftwrap_charges' => 0,
            'transaction_charges' => 0,
            'total_discount' => (string) $vendorOrder->discount,
            'sub_total' => (string) $vendorOrder->subtotal,
            'length' => 10,
            'breadth' => 10,
            'height' => 10,
            'weight' => $weight,
            'mode' => 'Surface',
        ];
    }

    protected function pickupLocationFor(VendorOrder $vendorOrder): string
    {
        return $this->settings->defaultPickupLocation()
            ?? $vendorOrder->vendor?->city
            ?? 'Default';
    }

    protected function addressLine(?OrderAddress $address): string
    {
        if (! $address) {
            return '';
        }

        return trim(implode(' ', array_filter([
            $address->address_line1,
            $address->address_line2,
            $address->landmark,
        ])));
    }

    protected function recordEvent(
        Shipment $shipment,
        ShipmentStatus $status,
        ?string $description = null,
        bool $isWebhook = false,
        ?array $payload = null,
        ?CarbonInterface $occurredAt = null,
    ): void {
        try {
            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'status' => $status->value,
                'location' => $payload['location'] ?? null,
                'description' => $description ?: $status->label(),
                'is_webhook' => $isWebhook,
                'payload' => $payload,
                'occurred_at' => $occurredAt ?? now(),
            ]);
        } catch (UniqueConstraintViolationException) {
        }
    }

    public function statusFromExternal(string $text): ?ShipmentStatus
    {
        $status = preg_replace('/\s+/', ' ', strtolower(trim($text))) ?? '';

        return match (true) {
            str_contains($status, 'out for delivery') => ShipmentStatus::OutForDelivery,
            str_contains($status, 'reattempt') => ShipmentStatus::Reattempting,
            str_contains($status, 'rto'), str_contains($status, 'return to origin'), str_contains($status, 'returned') => ShipmentStatus::RtoDelivered,
            str_contains($status, 'cancel') => ShipmentStatus::Cancelled,
            str_contains($status, 'deliver') => ShipmentStatus::Delivered,
            str_contains($status, 'in transit') => ShipmentStatus::InTransit,
            str_contains($status, 'pickup done'), str_contains($status, 'picked up'), str_contains($status, 'shipped') => ShipmentStatus::PickedUp,
            str_contains($status, 'undeliver'), str_contains($status, 'failed') => ShipmentStatus::Reattempting,
            default => null,
        };
    }
}
