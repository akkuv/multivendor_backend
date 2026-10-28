<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\V1\CategoryResource;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->with('children', 'parent')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('parent_id') && $request->integer('parent_id') !== 0, fn ($q) => $q->where('parent_id', $request->integer('parent_id')))
            ->when($request->input('only_roots', false), fn ($q) => $q->whereNull('parent_id'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->withCount('children')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(__('Categories fetched successfully.'), $categories, fn ($items) => CategoryResource::collection($items));
    }

    public function all(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->with('parent')
            ->withCount('products')
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get();

        return $this->success(__('All categories fetched successfully.'), CategoryResource::collection($categories));
    }

    public function tree(Request $request): JsonResponse
    {
        $categories = $this->categoryQuery($request)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return $this->success(__('Category tree fetched successfully.'), CategoryResource::collection($categories));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return $this->success(
            $this->actionMessage($category, 'created'),
            new CategoryResource($category),
            201,
        );
    }

    public function show(Category $category): JsonResponse
    {
        $category->load('children', 'parent');

        return $this->success(__('Category fetched successfully.'), new CategoryResource($category));
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        $category = $category->fresh(['children', 'parent']);

        return $this->success($this->actionMessage($category, 'updated'), new CategoryResource($category));
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->children()->count() > 0) {
            return $this->error(__('Cannot delete a category with child categories.'), null, 422);
        }

        $category->delete();

        return $this->success(__('Category deleted successfully.'));
    }

    public function toggleActive(Category $category): JsonResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        return $this->success(__('Category status updated.'), new CategoryResource($category->fresh()));
    }

    protected function categoryType(Category $category): string
    {
        $parent = $category->parent;

        return match (true) {
            $category->parent_id === null => 'category',
            $parent->parent_id === null => 'subcategory',
            default => 'child category',
        };
    }

    protected function actionMessage(Category $category, string $action): string
    {
        return ucfirst($this->categoryType($category)).' '.$action.' successfully.';
    }

    protected function categoryQuery(Request $request): Builder
    {
        return Category::query()
            ->with('children', 'parent')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));
    }
}
