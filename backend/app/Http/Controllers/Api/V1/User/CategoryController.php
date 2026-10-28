<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->with('children')
            ->withCount(['products' => fn ($q) => $q->live()])
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return $this->success(__('Categories fetched successfully.'), CategoryResource::collection($categories));
    }

    public function show(Category $category): JsonResponse
    {
        if (! $category->is_active) {
            return $this->error(__('Category not found.'), null, 404);
        }

        $category->load('children', 'products');

        return $this->success(__('Category fetched successfully.'), new CategoryResource($category));
    }
}
