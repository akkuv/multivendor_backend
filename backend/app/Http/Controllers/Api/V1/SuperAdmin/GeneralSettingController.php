<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Settings\GeneralSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeneralSettingController extends Controller
{
    public function __construct(protected GeneralSettingsService $settings) {}

    public function index(): JsonResponse
    {
        return $this->success(__('General settings fetched successfully.'), $this->settings->settings());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_name' => ['sometimes', 'string', 'max:255'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'logo' => ['sometimes', 'nullable', 'url'],
            'favicon' => ['sometimes', 'nullable', 'url'],
            'contact_email' => ['sometimes', 'nullable', 'email'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'timezone' => ['sometimes', 'string', 'max:100'],
        ]);

        $this->settings->settings()->update($data);
        $this->settings->forget();

        return $this->success(__('General settings updated successfully.'), $this->settings->settings());
    }
}
