<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportReviewRequest;
use App\Http\Resources\V1\ReviewResource;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reviews = Review::query()
            ->with(['user', 'product', 'vendor'])
            ->when($request->filled('status'), function ($q) use ($request): void {
                match (strtolower((string) $request->string('status'))) {
                    'pending' => $q->where('is_approved', false),
                    'approved' => $q->where('is_approved', true),
                    'hidden' => $q->where('is_hidden', true),
                    'reported' => $q->whereNotNull('reported_at'),
                    default => $q,
                };
            })
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', $request->integer('vendor_id')))
            ->when($request->filled('rating'), fn ($q) => $q->where('rating', $request->integer('rating')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(__('Reviews fetched successfully.'), $reviews, fn ($items) => ReviewResource::collection($items));
    }

    public function stats(): JsonResponse
    {
        $rows = Review::query()->get(['id', 'rating', 'is_approved', 'is_hidden', 'reported_at']);

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($rows as $row) {
            $distribution[(int) $row->rating] = ($distribution[(int) $row->rating] ?? 0) + 1;
        }

        return $this->success(__('Review stats fetched successfully.'), [
            'total' => $rows->count(),
            'average_rating' => round((float) $rows->avg('rating'), 1),
            'approved' => (int) $rows->where('is_approved', true)->count(),
            'pending' => (int) $rows->where('is_approved', false)->count(),
            'hidden' => (int) $rows->where('is_hidden', true)->count(),
            'reported' => (int) $rows->whereNotNull('reported_at')->count(),
            'rating_distribution' => $distribution,
        ]);
    }

    public function show(Review $review): JsonResponse
    {
        $review->load(['user', 'product', 'vendor']);

        return $this->success(__('Review fetched successfully.'), new ReviewResource($review));
    }

    public function approve(Review $review): JsonResponse
    {
        $review->forceFill(['is_approved' => true, 'is_hidden' => false])->save();

        return $this->success(__('Review approved successfully.'), new ReviewResource($review->load('user', 'product', 'vendor')));
    }

    public function hide(Review $review): JsonResponse
    {
        $review->forceFill(['is_hidden' => true])->save();

        return $this->success(__('Review hidden successfully.'), new ReviewResource($review->load('user', 'product', 'vendor')));
    }

    public function unhide(Review $review): JsonResponse
    {
        $review->forceFill(['is_hidden' => false])->save();

        return $this->success(__('Review unhidden successfully.'), new ReviewResource($review->load('user', 'product', 'vendor')));
    }

    public function report(ReportReviewRequest $request, Review $review): JsonResponse
    {
        $review->forceFill([
            'reported_at' => now(),
            'report_reason' => $request->input('reason') ?: 'Reported by moderation team.',
        ])->save();

        return $this->success(__('Review reported successfully.'), new ReviewResource($review->load('user', 'product', 'vendor')));
    }

    public function unreport(Review $review): JsonResponse
    {
        $review->forceFill(['reported_at' => null, 'report_reason' => null])->save();

        return $this->success(__('Review report cleared successfully.'), new ReviewResource($review->load('user', 'product', 'vendor')));
    }

    public function moderateImages(Request $request, Review $review): JsonResponse
    {
        $validated = $request->validate([
            'images' => ['required', 'array'],
            'images.*' => ['nullable', 'string', 'max:2048'],
        ]);

        $review->forceFill(['images' => $validated['images'] ?? []])->save();

        return $this->success(__('Review images updated successfully.'), new ReviewResource($review->load('user', 'product', 'vendor')));
    }

    public function destroy(Review $review): JsonResponse
    {
        $review->delete();

        return $this->success(__('Review deleted successfully.'));
    }
}
