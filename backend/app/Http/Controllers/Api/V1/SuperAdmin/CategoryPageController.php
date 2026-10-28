<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\HomepageSectionType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryPage;
use App\Models\CategoryPageSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryPageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pages = CategoryPage::query()
            ->with('category:id,slug,name,image,is_active')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($w) use ($search) {
                    $w->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', (bool) $request->input('is_active')))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate((int) ($request->input('per_page', 15)));

        return $this->paginated(__('Category pages fetched successfully.'), $pages, fn ($items) => $items->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id', 'unique:category_pages,category_id'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category = Category::findOrFail($data['category_id']);

        $data['slug'] = $category->slug;
        $data['title'] ??= $category->name;
        $data['sort_order'] ??= (int) CategoryPage::query()->max('sort_order') + 1;

        $page = CategoryPage::create($data);

        return $this->success(__('Category page created successfully.'), $page->load('category:id,slug,name,image'), 201);
    }

    public function show(CategoryPage $categoryPage): JsonResponse
    {
        $categoryPage->load('category:id,slug,name,image', 'sections');

        return $this->success(__('Category page fetched successfully.'), $categoryPage);
    }

    public function update(Request $request, CategoryPage $categoryPage): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $categoryPage->update($data);

        return $this->success(__('Category page updated successfully.'), $categoryPage->fresh()->load('category:id,slug,name,image'));
    }

    public function destroy(CategoryPage $categoryPage): JsonResponse
    {
        $categoryPage->delete();

        return $this->success(__('Category page deleted successfully.'));
    }

    public function toggle(CategoryPage $categoryPage): JsonResponse
    {
        $categoryPage->update(['is_active' => ! $categoryPage->is_active]);

        return $this->success(__('Category page status updated.'), $categoryPage->fresh());
    }

    /* ── Section CRUD ───────────────────────────────────────────────────── */

    public function storeSection(Request $request, CategoryPage $categoryPage): JsonResponse
    {
        $data = $this->validateSection($request, required: true);
        $data['category_page_id'] = $categoryPage->id;

        if (! isset($data['sort_order'])) {
            $data['sort_order'] = (int) $categoryPage->sections()->max('sort_order') + 1;
        }

        $section = CategoryPageSection::create($data);

        return $this->success(__('Section created successfully.'), $section, 201);
    }

    public function updateSection(Request $request, CategoryPageSection $section): JsonResponse
    {
        $section->update($this->validateSection($request, required: false));

        return $this->success(__('Section updated successfully.'), $section->fresh());
    }

    public function destroySection(CategoryPageSection $section): JsonResponse
    {
        $section->delete();

        return $this->success(__('Section deleted successfully.'));
    }

    public function reorderSections(Request $request, CategoryPage $categoryPage): JsonResponse
    {
        $data = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*.id' => ['integer', 'exists:category_page_sections,id'],
            'sections.*.is_active' => ['sometimes', 'boolean'],
        ]);

        foreach (array_values($data['sections']) as $index => $section) {
            CategoryPageSection::query()
                ->whereKey($section['id'])
                ->where('category_page_id', $categoryPage->id)
                ->update(['sort_order' => $index]);
        }

        return $this->success(__('Sections reordered successfully.'));
    }

    /** @return array<string, mixed> */
    protected function validateSection(Request $request, bool $required): array
    {
        $typeRule = $required
            ? ['required', 'in:'.implode(',', array_column(HomepageSectionType::cases(), 'value'))]
            : ['sometimes', 'in:'.implode(',', array_column(HomepageSectionType::cases(), 'value'))];

        return $request->validate([
            'type' => $typeRule,
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'config' => ['sometimes', 'nullable', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
