<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Question\StoreQuestionRequest;
use App\Http\Resources\V1\ProductQuestionResource;
use App\Models\Product;
use App\Models\ProductQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductQuestionController extends Controller
{
    /**
     * Visible questions (and answers) for a product.
     */
    public function index(Request $request, Product $product): JsonResponse
    {
        if (! $product->isLive()) {
            return $this->error(__('Product not found.'), null, 404);
        }

        $questions = $product->visibleQuestions()
            ->with(['user', 'answers.vendor'])
            ->orderByDesc('created_at')
            ->limit($request->integer('per_page', 20))
            ->get();

        return $this->success(__('Questions fetched successfully.'), ProductQuestionResource::collection($questions));
    }

    /**
     * The authenticated customer asks a question about a live product.
     */
    public function store(StoreQuestionRequest $request, Product $product): JsonResponse
    {
        if (! $product->isLive()) {
            return $this->error(__('Product not found.'), null, 404);
        }

        $question = $request->user()->productQuestions()->create([
            'product_id' => $product->id,
            'question' => $request->string('question')->toString(),
        ]);

        return $this->success(__('Question submitted successfully.'), new ProductQuestionResource($question->load('user', 'product')));
    }

    /**
     * Questions the customer has asked.
     */
    public function my(Request $request): JsonResponse
    {
        $questions = $request->user()
            ->productQuestions()
            ->with(['product', 'user', 'answers.vendor'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 10));

        return $this->success(__('My questions fetched successfully.'), ProductQuestionResource::collection($questions));
    }

    public function destroy(Request $request, ProductQuestion $productQuestion): JsonResponse
    {
        if ($productQuestion->user_id !== $request->user()->id) {
            return $this->error(__('You can only delete your own questions.'), null, 403);
        }

        $productQuestion->delete();

        return $this->success(__('Question deleted successfully.'));
    }
}
