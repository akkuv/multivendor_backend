<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentSettingsService;
use App\Services\Payment\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentSettingController extends Controller
{
    public function __construct(protected PaymentSettingsService $settings) {}

    public function index(): JsonResponse
    {
        return $this->success(__('Payment settings fetched successfully.'), $this->settings->masked());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'razorpay_enabled' => ['sometimes', 'boolean'],
            'cod_enabled' => ['sometimes', 'boolean'],
            'wallet_enabled' => ['sometimes', 'boolean'],
            'razorpay_key_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'razorpay_key_secret' => ['sometimes', 'nullable', 'string'],
            'razorpay_webhook_secret' => ['sometimes', 'nullable', 'string'],
            'razorpay_mode' => ['sometimes', 'in:test,live'],
            'cod_min_amount' => ['sometimes', 'numeric', 'min:0'],
            'cod_max_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'cod_fee' => ['sometimes', 'numeric', 'min:0'],
            'free_delivery_threshold' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        $this->settings->update($data);

        return $this->success(__('Payment settings updated successfully.'), $this->settings->masked());
    }

    public function testRazorpay(Request $request, RazorpayService $razorpay): JsonResponse
    {
        $data = $request->validate([
            'razorpay_key_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'razorpay_key_secret' => ['sometimes', 'nullable', 'string'],
        ]);

        $razorpay = $razorpay->withCredentials(
            $data['razorpay_key_id'] ?? null,
            $data['razorpay_key_secret'] ?? null,
        );

        if (! $razorpay->hasCredentials()) {
            return $this->error(__('Razorpay keys are not configured.'), null, 422);
        }

        try {
            $connected = $razorpay->checkConnection();
        } catch (\Throwable $e) {
            logger()->error('Razorpay connection test failed', [
                'message' => $e->getMessage(),
            ]);

            return $this->success(__('Razorpay connection failed.'), ['connected' => false, 'message' => $e->getMessage()]);
        }

        return $this->success(
            $connected ? 'Razorpay connection successful.' : 'Razorpay connection failed.',
            [
                'connected' => $connected,
                'message' => $connected ? 'Keys are valid.' : 'Razorpay rejected the provided keys.',
            ],
        );
    }
}
