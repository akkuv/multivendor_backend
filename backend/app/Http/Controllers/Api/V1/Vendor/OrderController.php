<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Enums\VendorOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ShipmentResource;
use App\Http\Resources\V1\VendorOrderResource;
use App\Models\OrderItem;
use App\Models\VendorOrder;
use App\Models\VendorProfile;
use App\Services\Order\OrderStatusService;
use App\Services\Shipping\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected ShippingService $shippingService,
        protected OrderStatusService $orderStatus,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VendorOrder::class);

        $vendor = $this->vendor($request);

        $vendorOrders = VendorOrder::query()
            ->with([
                'items.vendor',
                'order.user',
                'order.orderAddress',
                'order.shippingAddress',
                'statusHistories',
            ])
            ->where('vendor_id', $vendor->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->upper()))
            ->when($request->filled('search'), fn ($q) => $q->where('vendor_order_number', 'like', '%'.$request->string('search').'%'))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 12));

        return $this->paginated(__('Orders fetched successfully.'), $vendorOrders, fn ($items) => VendorOrderResource::collection($items));
    }

    public function show(Request $request, VendorOrder $vendorOrder): JsonResponse
    {
        $this->authorize('view', $vendorOrder);

        $vendorOrder->load([
            'items.vendor',
            'shipment',
            'order.user',
            'order.orderAddress',
            'order.shippingAddress',
            'statusHistories',
            'order.statusHistories',
        ]);

        return $this->success(__('Order fetched successfully.'), new VendorOrderResource($vendorOrder));
    }

    public function updateItemStatus(Request $request, OrderItem $orderItem): JsonResponse
    {
        $this->authorize('update', $orderItem);

        if (! $orderItem->vendorOrder) {
            return $this->error(__('Order item not found.'), null, 404);
        }

        $status = VendorOrderStatus::tryFrom(strtoupper((string) $request->string('status')));

        if ($status === null) {
            return $this->error(__('Invalid status.'), null, 422);
        }

        try {
            $vendorOrder = $this->orderStatus->changeVendorOrderStatus($orderItem->vendorOrder, $status, 'Item status updated by vendor.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        return $this->success(__('Order item status updated.'), [
            'vendor_order' => new VendorOrderResource($vendorOrder->fresh([
                'items.vendor',
                'order.user',
                'order.orderAddress',
                'statusHistories',
            ])),
        ]);
    }

    public function updateStatus(Request $request, VendorOrder $vendorOrder): JsonResponse
    {
        $this->authorize('update', $vendorOrder);

        $status = VendorOrderStatus::tryFrom(strtoupper((string) $request->string('status')));

        if ($status === null) {
            return $this->error(__('Invalid status.'), null, 422);
        }

        try {
            $vendorOrder = $this->orderStatus->changeVendorOrderStatus($vendorOrder, $status);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        return $this->success(__('Order status updated successfully.'), [
            'vendor_order' => new VendorOrderResource($vendorOrder->fresh([
                'items.vendor',
                'order.user',
                'order.orderAddress',
                'statusHistories',
            ])),
        ]);
    }

    /**
     * Courier rates for a vendor order so the vendor can compare prices before
     * committing the parcel to a carrier.
     */
    public function couriers(Request $request, VendorOrder $vendorOrder): JsonResponse
    {
        $this->authorize('view', $vendorOrder);

        $validated = $request->validate([
            'weight' => ['sometimes', 'numeric', 'min:0.1', 'max:100'],
        ]);

        $vendorOrder->loadMissing(['vendor', 'order.orderAddress', 'order.shippingAddress', 'order.user']);

        try {
            $rates = $this->shippingService->courierRates($vendorOrder, (float) ($validated['weight'] ?? 1.0));

            return $this->success(__('Courier rates fetched successfully.'), $rates);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return $this->error(__('Unable to fetch courier rates: ').$e->getMessage(), null, 502);
        }
    }

    public function ship(Request $request, VendorOrder $vendorOrder): JsonResponse
    {
        $this->authorize('update', $vendorOrder);

        $validated = $request->validate([
            'weight' => ['sometimes', 'numeric', 'min:0.1', 'max:100'],
            'courier_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        try {
            $shipment = $this->shippingService->ship(
                $vendorOrder,
                (float) ($validated['weight'] ?? 1.0),
                isset($validated['courier_id']) ? (int) $validated['courier_id'] : null,
            );

            return $this->success(__('Order shipped successfully.'), ['shipment' => new ShipmentResource($shipment)]);
        } catch (\Throwable $e) {
            return $this->error(__('Unable to ship order: ').$e->getMessage(), null, 502);
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
