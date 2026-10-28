<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\CommissionSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommissionSettingController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->success(__('Commission settings fetched successfully.'), CommissionSetting::all());
    }

    public function update(Request $request, CommissionSetting $commissionSetting): JsonResponse
    {
        $data = $request->validate([
            'rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'type' => ['sometimes', 'in:PERCENTAGE,FIXED'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $commissionSetting->update($data);

        return $this->success(__('Commission setting updated successfully.'), $commissionSetting->fresh());
    }
}
