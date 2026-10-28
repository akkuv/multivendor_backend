<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Review\StoreReviewRequest;
use App\Http\Resources\V1\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use App\Services\Review\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(protected ReviewService $reviewService) {}

    public function store(StoreReviewRequest $request): JsonResponse
    {
        $productId = $request->integer('product_id');

        if (! $productId) {
            return $this->error(__('The product id field is required.'), null, 422);
        }

        $product = Product::query()->findOrFail($productId);

        if (! $product->isLive()) {
            return $this->error(__('This product is no longer available.'), null, 404);
        }

        try {
            $review = $this->reviewService->submit($request->user(), $request->validated(), $product);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 409);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        $message = $review->wasRecentlyCreated
            ? 'Review submitted successfully.'
            : 'Review updated successfully.';

        if (! $review->is_approved) {
            $message .= ' It is pending approval.';
        }

        return $this->success($message, ['review' => new ReviewResource($review)]);
    }

    public function update(StoreReviewRequest $request, Review $review): JsonResponse
    {
        if ($review->user_id !== $request->user()->id) {
            return $this->error(__('You can only update your own reviews.'), null, 403);
        }

        $product = $review->product;

        if (! $product->isLive()) {
            return $this->error(__('This product is no longer available.'), null, 404);
        }

        try {
            $updated = $this->reviewService->submit(
                $request->user(),
                array_merge($request->validated(), ['product_id' => $product->id]),
                $product,
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), null, 409);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        return $this->success(__('Review updated successfully.'), ['review' => new ReviewResource($updated)]);
    }

    public function my(Request $request): JsonResponse
    {
        $reviews = $request->user()
            ->reviews()
            ->with(['user', 'product'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 10));

        return $this->paginated(__('Reviews fetched successfully.'),
            $reviews,
            fn ($items) => ReviewResource::collection($items),
        );
    }

    public function destroy(Request $request, Review $review): JsonResponse
    {
        try {
            $this->reviewService->deleteOwnedBy($request->user(), $review);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 403);
        }

        return $this->success(__('Review deleted successfully.'));
    }
}
