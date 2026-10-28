<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Exceptions\ShiprocketApiException;
use App\Http\Controllers\Controller;
use App\Services\Shipping\ShiprocketService;
use App\Services\Shipping\ShiprocketSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiprocketSettingController extends Controller
{
    public function __construct(
        protected ShiprocketSettingsService $settings,
        protected ShiprocketService $shiprocket,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success(__('Shiprocket settings fetched successfully.'), $this->settings->masked());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'environment' => ['sometimes', 'in:test,production'],
            'base_url' => ['sometimes', 'nullable', 'url'],
            'email' => ['sometimes', 'nullable', 'email'],
            'password' => ['sometimes', 'nullable', 'string', 'min:6'],
            'token' => ['sometimes', 'nullable', 'string'],
            'webhook_secret' => ['sometimes', 'nullable', 'string'],
            'default_pickup_location' => ['sometimes', 'nullable', 'string'],
            'pickup_locations' => ['sometimes', 'array'],
            'pickup_locations.*' => ['string'],
        ]);

        $this->settings->update($data);

        return $this->success(__('Shiprocket settings updated successfully.'), $this->settings->masked());
    }

    public function test(): JsonResponse
    {
        try {
            $token = $this->shiprocket->authenticate(true);

            return $this->success(__('Shiprocket connection successful.'), [
                'base_url' => $this->settings->baseUrl(),
                'token_generated' => filled($token),
            ]);
        } catch (ShiprocketApiException $e) {
            return $this->error(__('Shiprocket connection failed: ').$e->getMessage(), null, 502);
        }
    }

    /**
     * Pickup locations registered on the connected Shiprocket account, so an
     * admin can pick a default from real data instead of typing one blind.
     */
    public function pickupLocations(): JsonResponse
    {
        try {
            return $this->success(__('Pickup locations fetched successfully.'), [
                'pickup_locations' => $this->shiprocket->pickupLocations(),
            ]);
        } catch (ShiprocketApiException $e) {
            return $this->error(__('Unable to fetch pickup locations: ').$e->getMessage(), null, 502);
        }
    }
}
