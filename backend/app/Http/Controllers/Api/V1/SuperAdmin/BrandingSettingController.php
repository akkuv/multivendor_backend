<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Settings\BrandingSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandingSettingController extends Controller
{
    public function __construct(protected BrandingSettingsService $settings) {}

    public function index(): JsonResponse
    {
        return $this->success(__('Branding settings fetched successfully.'), $this->settings->settings());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'logo' => ['sometimes', 'nullable', 'url'],
            'dark_logo' => ['sometimes', 'nullable', 'url'],
            'favicon' => ['sometimes', 'nullable', 'url'],
            'primary_color' => ['sometimes', 'nullable', 'string', 'max:20'],
            'secondary_color' => ['sometimes', 'nullable', 'string', 'max:20'],
            'accent_color' => ['sometimes', 'nullable', 'string', 'max:20'],
            'facebook' => ['sometimes', 'nullable', 'url'],
            'instagram' => ['sometimes', 'nullable', 'url'],
            'twitter' => ['sometimes', 'nullable', 'url'],
            'youtube' => ['sometimes', 'nullable', 'url'],
            'linkedin' => ['sometimes', 'nullable', 'url'],
            'whatsapp' => ['sometimes', 'nullable', 'url'],
        ]);

        $this->settings->settings()->update($data);
        $this->settings->forget();

        return $this->success(__('Branding settings updated successfully.'), $this->settings->settings());
    }
}
