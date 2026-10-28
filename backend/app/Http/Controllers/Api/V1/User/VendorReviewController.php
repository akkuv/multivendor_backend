<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Review\StoreVendorReviewRequest;
use App\Http\Resources\V1\VendorReviewResource;
use App\Models\VendorProfile;
use App\Models\VendorReview;
use App\Services\Review\VendorReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorReviewController extends Controller
{
    public function __construct(protected VendorReviewService $vendorReviewService) {}

    public function store(StoreVendorReviewRequest $request): JsonResponse
    {
        $vendor = VendorProfile::query()->findOrFail($request->integer('vendor_id'));

        try {
            $review = $this->vendorReviewService->submit($request->user(), $request->validated(), $vendor);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 409);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        $message = $review->wasRecentlyCreated
            ? 'Vendor review submitted successfully.'
            : 'Vendor review updated successfully.';

        if (! $review->is_approved) {
            $message .= ' It is pending approval.';
        }

        return $this->success($message, ['review' => new VendorReviewResource($review)]);
    }

    public function my(Request $request): JsonResponse
    {
        $reviews = $request->user()
            ->vendorReviews()
            ->with(['user', 'vendor'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 10));

        return $this->paginated(__('Vendor reviews fetched successfully.'),
            $reviews,
            fn ($items) => VendorReviewResource::collection($items),
        );
    }

    public function destroy(Request $request, VendorReview $vendorReview): JsonResponse
    {
        try {
            $this->vendorReviewService->deleteOwnedBy($request->user(), $vendorReview);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success(__('Vendor review deleted successfully.'));
    }
}
