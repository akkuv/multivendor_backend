<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryPage;
use App\Services\CategoryPageService;
use Illuminate\Http\JsonResponse;

class CategoryPageController extends Controller
{
    public function __construct(
        private readonly CategoryPageService $service,
    ) {}

    public function show(string $slug): JsonResponse
    {
        $custom = CategoryPage::where('slug', $slug)->where('is_active', true)->with('category')->first();

        if ($custom) {
            $category = $custom->category;
            if (! $category->is_active) {
                return $this->error(__('Category not found.'), null, 404);
            }

            return $this->success(__('Category page fetched successfully.'), $this->service->build($category, $custom));
        }

        $category = Category::where('slug', $slug)->where('is_active', true)->first();

        if (! $category) {
            return $this->error(__('Category not found.'), null, 404);
        }

        return $this->success(__('Category page fetched successfully.'), $this->service->build($category));
    }
}
