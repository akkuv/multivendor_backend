<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ShipmentEventResource;
use App\Http\Resources\V1\ShipmentResource;
use App\Models\Shipment;
use App\Models\VendorProfile;
use App\Services\Shipping\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function __construct(protected ShippingService $shippingService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shipment::class);

        $vendor = $this->vendor($request);

        $shipments = Shipment::query()
            ->with(['order.user', 'vendorOrder', 'events'])
            ->whereHas('vendorOrder', function ($query) use ($vendor): void {
                $query->where('vendor_id', $vendor->id);
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $status = ShipmentStatus::tryFrom(strtoupper((string) $request->string('status')));

                if ($status) {
                    $query->where('status', $status->value);
                }
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search');
                $query->where(fn ($sub) => $sub
                    ->where('tracking_number', 'like', "%{$search}%")
                    ->orWhere('courier_name', 'like', "%{$search}%"));
            })
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 12));

        return $this->paginated(__('Shipments fetched successfully.'), $shipments, fn ($items) => ShipmentResource::collection($items));
    }

    public function show(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('view', $shipment);

        $shipment->load(['order.user', 'vendorOrder', 'events']);

        return $this->success(__('Shipment fetched successfully.'), new ShipmentResource($shipment));
    }

    public function events(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('view', $shipment);

        $shipment->load('events');

        return $this->success(__('Shipment events fetched successfully.'), ShipmentEventResource::collection($shipment->events));
    }

    public function pickup(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('update', $shipment);

        try {
            $shipment = $this->shippingService->requestPickup(
                $shipment,
                $request->string('pickup_date')->toString() ?: null,
                $request->string('pickup_time')->toString() ?: null,
            );

            return $this->success(__('Pickup scheduled successfully.'), ['shipment' => new ShipmentResource($shipment->load('events'))]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), null, 422);
        }
    }

    public function retry(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('update', $shipment);

        if ($shipment->status !== ShipmentStatus::Failed) {
            return $this->error(__('Only failed shipments can be retried.'), null, 422);
        }

        try {
            $shipment = $this->shippingService->retry(
                $shipment->vendorOrder,
                $shipment,
                $request->float('weight', 1.0),
            );

            return $this->success(__('Shipment retried successfully.'), ['shipment' => new ShipmentResource($shipment->load('events'))]);
        } catch (\Throwable $e) {
            return $this->error(__('Unable to retry shipment: ').$e->getMessage(), null, 502);
        }
    }

    public function label(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('view', $shipment);

        try {
            return $this->success(__('Label resolved successfully.'), [
                'label_url' => $this->shippingService->label($shipment),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return $this->error(__('Unable to generate label: ').$e->getMessage(), null, 502);
        }
    }

    public function manifest(Request $request): JsonResponse
    {
        $this->vendor($request);

        $data = $request->validate([
            'shipment_ids' => ['required', 'array', 'min:1'],
            'shipment_ids.*' => ['integer'],
        ]);

        $ids = Shipment::query()
            ->whereIn('id', $data['shipment_ids'])
            ->whereHas('vendorOrder', fn ($query) => $query->where('vendor_id', $this->vendor($request)->id))
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return $this->error(__('No shipments found for this vendor.'), null, 404);
        }

        try {
            return $this->success(__('Manifest generated successfully.'), [
                'manifest_url' => $this->shippingService->manifest($ids),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return $this->error(__('Unable to generate manifest: ').$e->getMessage(), null, 502);
        }
    }

    public function cancel(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('update', $shipment);

        try {
            $shipment = $this->shippingService->cancel($shipment);

            return $this->success(__('Shipment cancelled successfully.'), ['shipment' => new ShipmentResource($shipment->load('events'))]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return $this->error(__('Unable to cancel shipment: ').$e->getMessage(), null, 502);
        }
    }

    protected function vendor(Request $request): VendorProfile
    {
        $vendor = $request->user()->vendorProfile;

        if (! $vendor) {
            abort(404, __('Vendor profile not found.'));
        }

        return $vendor;
    }
}
