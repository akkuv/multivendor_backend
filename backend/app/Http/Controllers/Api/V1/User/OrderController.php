<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use App\Services\Order\OrderService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected PaymentService $paymentService,
    ) {}

    public function store(PlaceOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->createFromCart(
                $request->user(),
                $request->integer('address_id'),
                $request->input('coupon_code'),
                $request->input('payment_method', 'razorpay'),
                $request->filled('wallet_amount') ? $request->float('wallet_amount') : null,
                $request->input('idempotency_key'),
            );

            $payment = $this->paymentService->createOrderPayment($order, $order->payment_method);

            return $this->success(__('Order placed successfully.'), [
                'order' => new OrderResource($order->load('items', 'shippingAddress')),
                'payment' => $payment['razorpay'],
            ], 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, 422);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $orders = $request->user()->orders()
            ->with('items.product', 'items.productReturns', 'shippingAddress', 'shipment', 'orderAddress', 'vendorOrders')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 10));

        return $this->paginated(__('Orders fetched successfully.'), $orders, fn ($items) => OrderResource::collection($items));
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load([
            'items.vendor',
            'items.product',
            'items.productReturns',
            'shippingAddress',
            'orderAddress',
            'statusHistories',
            'shipment',
            'vendorOrders.vendor',
            'vendorOrders.items.vendor',
            'vendorOrders.statusHistories',
        ]);

        return $this->success(__('Order fetched successfully.'), [
            'order' => new OrderResource($order),
        ]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        try {
            $order = $this->orderService->cancel($request->user(), $order, $request->input('reason', ''));

            return $this->success(__('Order cancelled successfully.'), [
                'order' => new OrderResource($order->load('items')),
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), null, 422);
        }
    }
}
