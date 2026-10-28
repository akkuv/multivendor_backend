<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VendorRegisterRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;

class VendorAuthController extends Controller
{
    public function __construct(protected AuthService $authService) {}

    public function register(VendorRegisterRequest $request): JsonResponse
    {
        $result = $this->authService->registerVendor($request->validated());

        return $this->success(__('Vendor registration submitted. Awaiting admin approval.'), [
            'user' => new UserResource($result['user']->load('vendorProfile', 'wallet')),
            'vendor' => $result['vendor'],
            'token' => $result['token'],
        ], 201);
    }
}
