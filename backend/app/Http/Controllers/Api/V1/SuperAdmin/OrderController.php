<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\OrderStatus;
use App\Enums\VendorOrderStatus;
use App\Http\Controllers\Concerns\FiltersCreatedRange;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\OrderResource;
use App\Http\Resources\V1\VendorOrderResource;
use App\Models\Order;
use App\Models\VendorOrder;
use App\Services\Order\OrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use FiltersCreatedRange;

    public function __construct(protected OrderStatusService $orderStatus) {}

    public function index(Request $request): JsonResponse
    {
        $orders = $this->applyCreatedRange(Order::query(), $request)
            ->with([
                'user',
                'items',
                'shippingAddress',
                'orderAddress',
                'statusHistories',
                'vendorOrders.vendor',
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('order_status', $request->string('status')->upper()))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->string('payment_status')->upper()))
            // Matching a customer's name, email or phone as well as the order
            // number lets the "new order query" search find the right order
            // even when only the customer is known.
            ->when($request->filled('search'), fn ($q) => $q->where(function ($w) use ($request) {
                $search = $request->string('search');

                $w->where('order_number', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($u) => $u
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%'));
            }))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 12));

        return $this->paginated(__('Orders fetched successfully.'), $orders, fn ($items) => OrderResource::collection($items));
    }

    public function show(Order $order): JsonResponse
    {
        $order->load([
            'user',
            'items.vendor',
            'shippingAddress',
            'orderAddress',
            'statusHistories',
            'vendorOrders.vendor',
            'vendorOrders.items.vendor',
            'vendorOrders.statusHistories',
            'vendorOrders.shipment',
        ]);

        return $this->success(__('Order fetched successfully.'), new OrderResource($order));
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $status = OrderStatus::tryFrom(strtoupper((string) $request->string('status')));

        if ($status === null) {
            return $this->error(__('Invalid order status.'), null, 422);
        }

        try {
            $order = $this->orderStatus->changeOrderStatus($order, $status)->load([
                'user',
                'items',
                'shippingAddress',
                'orderAddress',
                'statusHistories',
                'vendorOrders.vendor',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        return $this->success(__('Order status updated successfully.'), [
            'order' => new OrderResource($order),
        ]);
    }

    public function vendorOrders(Request $request): JsonResponse
    {
        $vendorOrders = VendorOrder::query()
            ->with([
                'vendor',
                'items.vendor',
                'order.user',
                'order.orderAddress',
                'statusHistories',
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->upper()))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', $request->integer('vendor_id')))
            ->when($request->filled('search'), fn ($q) => $q->where('vendor_order_number', 'like', '%'.$request->string('search').'%'))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 12));

        return $this->paginated(__('Vendor orders fetched successfully.'), $vendorOrders, fn ($items) => VendorOrderResource::collection($items));
    }

    public function showVendorOrder(VendorOrder $vendorOrder): JsonResponse
    {
        $vendorOrder->load([
            'vendor',
            'items.vendor',
            'shipment',
            'order.user',
            'order.orderAddress',
            'order.statusHistories',
            'statusHistories',
        ]);

        return $this->success(__('Vendor order fetched successfully.'), [
            'vendor_order' => new VendorOrderResource($vendorOrder),
        ]);
    }

    public function updateVendorOrderStatus(Request $request, VendorOrder $vendorOrder): JsonResponse
    {
        $status = VendorOrderStatus::tryFrom(strtoupper((string) $request->string('status')));

        if ($status === null) {
            return $this->error(__('Invalid order status.'), null, 422);
        }

        try {
            $vendorOrder = $this->orderStatus->changeVendorOrderStatus($vendorOrder, $status, 'Status managed by admin.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        return $this->success(__('Vendor order status updated successfully.'), [
            'vendor_order' => new VendorOrderResource($vendorOrder->fresh([
                'vendor',
                'items.vendor',
                'order.user',
                'order.orderAddress',
                'statusHistories',
            ])),
        ]);
    }
}
