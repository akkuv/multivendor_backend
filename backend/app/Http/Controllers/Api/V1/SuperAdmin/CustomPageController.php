<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\CustomPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomPageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pages = CustomPage::query()
            ->withCount('sections')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(fn ($w) => $w->where('title', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%"));
            })
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', (bool) $request->input('is_active')))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate((int) ($request->input('per_page', 15)));

        return $this->paginated(__('Pages fetched successfully.'), $pages, fn ($items) => $items->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $base = CustomPage::normalizeSlug((string) ($data['slug'] ?? ''));
        $data['slug'] = $this->uniqueSlug($base !== '' ? $base : CustomPage::normalizeSlug($data['title']));
        $data['sort_order'] ??= (int) CustomPage::query()->max('sort_order') + 1;

        $page = CustomPage::create($data);

        return $this->success(__('Page created successfully.'), $page, 201);
    }

    public function show(CustomPage $page): JsonResponse
    {
        return $this->success(__('Page fetched successfully.'), $page->loadCount('sections'));
    }

    public function update(Request $request, CustomPage $page): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('slug', $data)) {
            $base = CustomPage::normalizeSlug((string) ($data['slug'] ?? ''));
            if ($base === '') {
                $base = CustomPage::normalizeSlug((string) ($data['title'] ?? $page->title));
            }
            $data['slug'] = $this->uniqueSlug($base, $page->id);
        }

        $page->update($data);

        return $this->success(__('Page updated successfully.'), $page->fresh()->loadCount('sections'));
    }

    public function destroy(CustomPage $page): JsonResponse
    {
        $page->delete();

        return $this->success(__('Page deleted successfully.'));
    }

    public function toggle(CustomPage $page): JsonResponse
    {
        $page->update(['is_active' => ! $page->is_active]);

        return $this->success(__('Page status updated.'), $page->fresh()->loadCount('sections'));
    }

    /** Return a slug that is unique across custom pages, suffixing -2, -3, … as needed. */
    protected function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $base = $base !== '' ? $base : 'page';
        $slug = $base;

        for ($suffix = 2; CustomPage::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
