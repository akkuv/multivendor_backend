<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProfileController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success(__('Admin profile.'), new UserResource($user));
    }
}
