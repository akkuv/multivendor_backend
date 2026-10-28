<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Settings\SecuritySettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecuritySettingController extends Controller
{
    public function __construct(protected SecuritySettingsService $settings) {}

    public function index(): JsonResponse
    {
        return $this->success(__('Security settings fetched successfully.'), $this->settings->settings());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login_attempt_limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'lockout_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'otp_expiry_minutes' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'session_timeout_minutes' => ['sometimes', 'integer', 'min:5', 'max:1440'],
            'remember_me_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'maintenance_mode' => ['sometimes', 'boolean'],
            'maintenance_message' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $this->settings->settings()->update($data);
        $this->settings->forget();

        return $this->success(__('Security settings updated successfully.'), $this->settings->settings());
    }
}
