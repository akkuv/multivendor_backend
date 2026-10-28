<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportReviewRequest;
use App\Http\Resources\V1\VendorReviewResource;
use App\Models\VendorReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reviews = VendorReview::query()
            ->with(['user', 'vendor'])
            ->when($request->filled('status'), function ($q) use ($request): void {
                match (strtolower((string) $request->string('status'))) {
                    'pending' => $q->where('is_approved', false),
                    'approved' => $q->where('is_approved', true),
                    'hidden' => $q->where('is_hidden', true),
                    'reported' => $q->whereNotNull('reported_at'),
                    default => $q,
                };
            })
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', $request->integer('vendor_id')))
            ->when($request->filled('rating'), fn ($q) => $q->where('rating', $request->integer('rating')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(__('Vendor reviews fetched successfully.'), $reviews, fn ($items) => VendorReviewResource::collection($items));
    }

    public function show(VendorReview $vendorReview): JsonResponse
    {
        $vendorReview->load(['user', 'vendor']);

        return $this->success(__('Vendor review fetched successfully.'), new VendorReviewResource($vendorReview));
    }

    public function approve(VendorReview $vendorReview): JsonResponse
    {
        $vendorReview->forceFill(['is_approved' => true, 'is_hidden' => false])->save();

        return $this->success(__('Vendor review approved successfully.'), new VendorReviewResource($vendorReview->load('user', 'vendor')));
    }

    public function hide(VendorReview $vendorReview): JsonResponse
    {
        $vendorReview->forceFill(['is_hidden' => true])->save();

        return $this->success(__('Vendor review hidden successfully.'), new VendorReviewResource($vendorReview->load('user', 'vendor')));
    }

    public function unhide(VendorReview $vendorReview): JsonResponse
    {
        $vendorReview->forceFill(['is_hidden' => false])->save();

        return $this->success(__('Vendor review unhidden successfully.'), new VendorReviewResource($vendorReview->load('user', 'vendor')));
    }

    public function report(ReportReviewRequest $request, VendorReview $vendorReview): JsonResponse
    {
        $vendorReview->forceFill([
            'reported_at' => now(),
            'report_reason' => $request->input('reason') ?: 'Reported by moderation team.',
        ])->save();

        return $this->success(__('Vendor review reported successfully.'), new VendorReviewResource($vendorReview->load('user', 'vendor')));
    }

    public function unreport(VendorReview $vendorReview): JsonResponse
    {
        $vendorReview->forceFill(['reported_at' => null, 'report_reason' => null])->save();

        return $this->success(__('Vendor review report cleared successfully.'), new VendorReviewResource($vendorReview->load('user', 'vendor')));
    }

    public function destroy(VendorReview $vendorReview): JsonResponse
    {
        $vendorReview->delete();

        return $this->success(__('Vendor review deleted successfully.'));
    }
}
