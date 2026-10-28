<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportReviewRequest;
use App\Http\Resources\V1\ProductQuestionResource;
use App\Models\ProductQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductQuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $questions = ProductQuestion::query()
            ->with(['user', 'product', 'answers.vendor'])
            ->when($request->filled('status'), function ($q) use ($request): void {
                match (strtolower((string) $request->string('status'))) {
                    'hidden' => $q->where('is_hidden', true),
                    'reported' => $q->whereNotNull('reported_at'),
                    'answered' => $q->where('is_answered', true),
                    'unanswered' => $q->where('is_answered', false),
                    default => $q,
                };
            })
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(__('Questions fetched successfully.'), $questions, fn ($items) => ProductQuestionResource::collection($items));
    }

    public function show(ProductQuestion $productQuestion): JsonResponse
    {
        $productQuestion->load(['user', 'product', 'answers.vendor']);

        return $this->success(__('Question fetched successfully.'), new ProductQuestionResource($productQuestion));
    }

    public function hide(ProductQuestion $productQuestion): JsonResponse
    {
        $productQuestion->forceFill(['is_hidden' => true])->save();

        return $this->success(__('Question hidden successfully.'), new ProductQuestionResource($productQuestion->load(['user', 'product', 'answers.vendor'])));
    }

    public function unhide(ProductQuestion $productQuestion): JsonResponse
    {
        $productQuestion->forceFill(['is_hidden' => false])->save();

        return $this->success(__('Question unhidden successfully.'), new ProductQuestionResource($productQuestion->load(['user', 'product', 'answers.vendor'])));
    }

    public function report(ReportReviewRequest $request, ProductQuestion $productQuestion): JsonResponse
    {
        $productQuestion->forceFill([
            'reported_at' => now(),
            'report_reason' => $request->input('reason') ?: 'Reported by moderation team.',
        ])->save();

        return $this->success(__('Question reported successfully.'), new ProductQuestionResource($productQuestion->load(['user', 'product', 'answers.vendor'])));
    }

    public function unreport(ProductQuestion $productQuestion): JsonResponse
    {
        $productQuestion->forceFill(['reported_at' => null, 'report_reason' => null])->save();

        return $this->success(__('Question report cleared successfully.'), new ProductQuestionResource($productQuestion->load(['user', 'product', 'answers.vendor'])));
    }

    public function destroy(ProductQuestion $productQuestion): JsonResponse
    {
        $productQuestion->delete();

        return $this->success(__('Question deleted successfully.'));
    }
}
