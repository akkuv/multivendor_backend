<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\CmsPageType;
use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CmsPageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pages = CmsPage::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');

                return $q->where(fn ($w) => $w->where('title', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%"));
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate((int) ($request->input('per_page', 15)));

        return $this->paginated(__('CMS pages fetched successfully.'), $pages, fn ($items) => $items->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['sometimes', 'in:'.CmsPageType::Page->value.','.CmsPageType::Faq->value],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'alpha_dash', 'unique:cms_pages,slug'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:50'],
            'content' => ['sometimes', 'nullable', 'string'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $data['type'] = $data['type'] ?? CmsPageType::Page->value;
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);

        $page = CmsPage::create($data);

        return $this->success(__('CMS page created successfully.'), $page, 201);
    }

    public function show(CmsPage $cmsPage): JsonResponse
    {
        return $this->success(__('CMS page fetched successfully.'), $cmsPage);
    }

    public function update(Request $request, CmsPage $cmsPage): JsonResponse
    {
        $data = $request->validate([
            'type' => ['sometimes', 'in:'.CmsPageType::Page->value.','.CmsPageType::Faq->value],
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'alpha_dash', 'unique:cms_pages,slug,'.$cmsPage->id],
            'icon' => ['sometimes', 'nullable', 'string', 'max:50'],
            'content' => ['sometimes', 'nullable', 'string'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $cmsPage->update($data);

        return $this->success(__('CMS page updated successfully.'), $cmsPage->fresh());
    }

    public function destroy(CmsPage $cmsPage): JsonResponse
    {
        $cmsPage->delete();

        return $this->success(__('CMS page deleted successfully.'));
    }

    /** Reorder CMS pages via an ordered list of ids. */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer', 'exists:cms_pages,id'],
        ]);

        foreach (array_values($data['ordered_ids']) as $index => $id) {
            CmsPage::query()->whereKey($id)->update(['sort_order' => $index]);
        }

        return $this->success(__('CMS pages reordered successfully.'));
    }

    public function toggle(CmsPage $cmsPage): JsonResponse
    {
        $cmsPage->update(['is_active' => ! $cmsPage->is_active]);

        return $this->success(__('CMS page status updated.'), $cmsPage->fresh());
    }
}
