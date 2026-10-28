<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Services\Settings\WebsiteSettingsService;
use Illuminate\Http\JsonResponse;

class WebsiteContentController extends Controller
{
    public function __construct(protected WebsiteSettingsService $settings) {}

    public function show(): JsonResponse
    {
        return $this->success(__('Website content fetched successfully.'), $this->settings->publicContent());
    }
}
