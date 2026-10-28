<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Enums\VendorStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StorefrontVendorResource;
use App\Http\Resources\V1\VendorReviewResource;
use App\Models\VendorProfile;
use Illuminate\Http\JsonResponse;

class VendorController extends Controller
{
    public function index(): JsonResponse
    {
        $vendors = VendorProfile::query()
            ->where('is_approved', true)
            ->where('status', VendorStatus::Approved->value)
            ->withCount('products')
            ->orderBy('store_name')
            ->get();

        return $this->success(__('Vendors fetched successfully.'), StorefrontVendorResource::collection($vendors));
    }

    public function show(VendorProfile $vendor): JsonResponse
    {
        if (! $vendor->is_approved && $vendor->status !== VendorStatus::Approved->value) {
            return $this->error(__('Vendor not found.'), null, 404);
        }

        $vendor->loadCount('products');

        return $this->success(__('Vendor fetched successfully.'), new StorefrontVendorResource($vendor));
    }

    public function reviews(VendorProfile $vendor): JsonResponse
    {
        if (! $vendor->is_approved && $vendor->status !== VendorStatus::Approved->value) {
            return $this->error(__('Vendor not found.'), null, 404);
        }

        $reviews = $vendor->approvedReviews()
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        return $this->success(__('Vendor reviews fetched successfully.'), VendorReviewResource::collection($reviews));
    }
}
