<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVendorProfileRequest;
use App\Http\Resources\V1\VendorResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendorProfile;

        if (! $vendor) {
            return $this->error(__('Vendor profile not found.'), null, 404);
        }

        return $this->success(__('Vendor profile fetched successfully.'), new VendorResource($vendor, [
            'products_count' => $vendor->products()->count(),
        ]));
    }

    public function update(UpdateVendorProfileRequest $request): JsonResponse
    {
        $vendor = $request->user()->vendorProfile;

        if (! $vendor) {
            return $this->error(__('Vendor profile not found.'), null, 404);
        }

        $vendor->update($request->validated());

        return $this->success(__('Vendor profile updated successfully.'), new VendorResource($vendor->fresh(), [
            'products_count' => $vendor->fresh()->products()->count(),
        ]));
    }
}
