<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Question\StoreAnswerRequest;
use App\Http\Resources\V1\ProductQuestionResource;
use App\Models\ProductQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductAnswerController extends Controller
{
    public function store(StoreAnswerRequest $request, ProductQuestion $productQuestion): JsonResponse
    {
        $vendor = $request->user()->vendorProfile;

        $product = $productQuestion->product;

        if (! $product || $product->vendor_id !== $vendor->id) {
            return $this->error(__('You can only answer questions about your own products.'), null, 403);
        }

        $answer = $productQuestion->answers()->create([
            'vendor_id' => $vendor->id,
            'answer' => $request->string('answer')->toString(),
        ]);

        $productQuestion->update(['is_answered' => true]);

        return $this->success(
            'Answer submitted successfully.',
            new ProductQuestionResource($productQuestion->load(['user', 'product', 'answers.vendor']))
        );
    }

    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendorProfile;

        $questions = ProductQuestion::query()
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->with(['product', 'user', 'answers.vendor'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 10));

        return $this->success(__('Vendor questions fetched successfully.'), ProductQuestionResource::collection($questions));
    }
}
