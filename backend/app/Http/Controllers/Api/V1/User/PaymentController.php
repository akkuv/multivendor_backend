<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyPaymentRequest;
use App\Models\Order;
use App\Services\Payment\PaymentService;
use App\Services\Payment\PaymentSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected PaymentSettingsService $settings,
    ) {}

    public function options(): JsonResponse
    {
        return $this->success(__('Available payment methods fetched successfully.'), $this->settings->publicOptions());
    }

    public function checkout(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return $this->error(__('Order not found.'), null, 404);
        }

        try {
            $payment = $this->paymentService->createOrderPayment($order, $request->input('method', $order->payment_method ?: 'razorpay'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        return $this->success(__('Checkout initiated.'), $payment);
    }

    public function verify(VerifyPaymentRequest $request): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)
            ->findOrFail($request->integer('order_id'));

        $result = $this->paymentService->verifyAndCapture($order, $request->validated());

        if (! $result) {
            $this->paymentService->markFailed($order, 'Payment verification failed.');

            return $this->error(__('Payment verification failed.'), null, 400);
        }

        return $this->success(__('Payment verified successfully.'), [
            'order' => $order->fresh(),
            'payment_status' => $order->payment_status,
        ]);
    }

    public function status(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return $this->error(__('Order not found.'), null, 404);
        }

        return $this->success(__('Payment status.'), [
            'order_number' => $order->order_number,
            'payment_status' => $order->payment_status,
            'order_status' => $order->order_status,
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature', '');

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return $this->error(__('Invalid webhook payload.'), null, 400);
        }

        try {
            $webhook = $this->paymentService->handleWebhook($event, $payload, $signature);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            logger()->error('Webhook processing error', ['message' => $e->getMessage()]);

            return $this->error(__('Webhook processing failed.'), null, 500);
        }

        return $this->success(__('Webhook received.'), [
            'id' => $webhook->id,
            'event' => $webhook->event,
            'status' => $webhook->status,
        ]);
    }
}
