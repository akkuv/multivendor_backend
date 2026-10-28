<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Shipping\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentWebhookController extends Controller
{
    public function __construct(protected ShippingService $shippingService) {}

    public function handle(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $signature = (string) $request->header('X-Shiprocket-Signature', '');

        try {
            $webhook = $this->shippingService->handleWebhook($rawPayload, $signature, $request->ip());

            return $this->success(__('Webhook processed.'), [
                'id' => $webhook->id,
                'status' => $webhook->status,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 401);
        } catch (\Throwable $e) {
            logger()->error('Shiprocket webhook failed', ['error' => $e->getMessage()]);

            return $this->error(__('Webhook processing failed.'), null, 500);
        }
    }
}
