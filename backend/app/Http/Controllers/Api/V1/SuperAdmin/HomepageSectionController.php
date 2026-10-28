<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\HomepageSectionType;
use App\Http\Controllers\Controller;
use App\Models\HomepageSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomepageSectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sections = HomepageSection::query()
            ->where('custom_page_id', $this->pageId($request))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', (bool) $request->input('is_active')))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->success(__('Homepage sections fetched successfully.'), $sections);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['custom_page_id'] ??= $this->pageId($request);

        if (! isset($data['sort_order'])) {
            $data['sort_order'] = (int) HomepageSection::query()
                ->where('custom_page_id', $data['custom_page_id'])
                ->max('sort_order') + 1;
        }

        $section = HomepageSection::create($data);

        return $this->success(__('Homepage section created successfully.'), $section, 201);
    }

    public function show(HomepageSection $homepageSection): JsonResponse
    {
        return $this->success(__('Homepage section fetched successfully.'), $homepageSection);
    }

    public function update(Request $request, HomepageSection $homepageSection): JsonResponse
    {
        $homepageSection->update($this->validated($request));

        return $this->success(__('Homepage section updated successfully.'), $homepageSection->fresh());
    }

    public function destroy(HomepageSection $homepageSection): JsonResponse
    {
        $homepageSection->delete();

        return $this->success(__('Homepage section deleted successfully.'));
    }

    /** Reorder sections and update visibility flags from an ordered payload. */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*.id' => ['integer', 'exists:homepage_sections,id'],
            'sections.*.is_active' => ['sometimes', 'boolean'],
        ]);

        foreach (array_values($data['sections']) as $index => $section) {
            HomepageSection::query()
                ->whereKey($section['id'])
                ->update(['sort_order' => $index]);
        }

        return $this->success(__('Homepage sections reordered successfully.'));
    }

    public function toggle(HomepageSection $homepageSection): JsonResponse
    {
        $homepageSection->update(['is_active' => ! $homepageSection->is_active]);

        return $this->success(__('Homepage section status updated.'), $homepageSection->fresh());
    }

    /** Resolve the scoped page id from the request, treating blank/zero as the homepage. */
    protected function pageId(Request $request): ?int
    {
        $value = $request->input('custom_page_id');

        if ($value === null || $value === '' || (int) $value === 0) {
            return null;
        }

        return (int) $value;
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'custom_page_id' => ['sometimes', 'nullable', 'integer', 'exists:custom_pages,id'],
            'type' => ['required', 'in:'.implode(',', array_column(HomepageSectionType::cases(), 'value'))],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'config' => ['sometimes', 'nullable', 'array'],
            'config.limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'config.category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'config.category_ids' => ['sometimes', 'array'],
            'config.category_ids.*' => ['integer', 'exists:categories,id'],
            'config.product_ids' => ['sometimes', 'array'],
            'config.product_ids.*' => ['integer', 'exists:products,id'],
            'config.items' => ['sometimes', 'array'],
            'config.items.*.category_id' => ['nullable', 'sometimes', 'integer', 'exists:categories,id'],
            'config.items.*.icon' => ['nullable', 'sometimes', 'string', 'max:40'],
            'config.items.*.title' => ['nullable', 'sometimes', 'string', 'max:255'],
            'config.items.*.subtitle' => ['nullable', 'sometimes', 'string', 'max:255'],
            'config.items.*.description' => ['nullable', 'sometimes', 'string', 'max:1000'],
            'config.items.*.image' => ['nullable', 'sometimes', 'string', 'max:2048'],
            'config.items.*.price' => ['nullable', 'sometimes', 'string', 'max:64'],
            'config.items.*.mobile_image' => ['nullable', 'sometimes', 'string', 'max:2048'],
            'config.items.*.name' => ['nullable', 'sometimes', 'string', 'max:255'],
            'config.items.*.author_name' => ['nullable', 'sometimes', 'string', 'max:150'],
            'config.items.*.author_role' => ['nullable', 'sometimes', 'string', 'max:150'],
            'config.items.*.author_avatar' => ['nullable', 'sometimes', 'string', 'max:2048'],
            'config.items.*.content' => ['nullable', 'sometimes', 'string', 'max:20000'],
            'config.items.*.rating' => ['nullable', 'sometimes', 'integer', 'min:1', 'max:5'],
            'config.items.*.button_text' => ['nullable', 'sometimes', 'string', 'max:255'],
            'config.items.*.button_url' => ['nullable', 'sometimes', 'string', 'max:1024'],
            'config.items.*.secondary_button_text' => ['nullable', 'sometimes', 'string', 'max:255'],
            'config.items.*.secondary_button_url' => ['nullable', 'sometimes', 'string', 'max:1024'],
            'config.items.*.url' => ['nullable', 'sometimes', 'string', 'max:1024'],
            'config.items.*.video' => ['nullable', 'sometimes', 'string', 'max:2048'],
            'config.items.*.poster' => ['nullable', 'sometimes', 'string', 'max:2048'],
            'config.items.*.caption' => ['nullable', 'sometimes', 'string', 'max:1000'],
            'config.items.*.permalink' => ['nullable', 'sometimes', 'string', 'max:1024'],
            'config.items.*.likes' => ['nullable', 'sometimes', 'string', 'max:40'],
            'config.items.*.comments' => ['nullable', 'sometimes', 'string', 'max:40'],
            'config.items.*.question' => ['nullable', 'sometimes', 'string', 'max:1000'],
            'config.items.*.answer' => ['nullable', 'sometimes', 'string', 'max:20000'],
            'config.html' => ['nullable', 'sometimes', 'string', 'max:200000'],
            'config.title' => ['nullable', 'sometimes', 'string', 'max:255'],
            'config.description' => ['nullable', 'sometimes', 'string', 'max:1000'],
            'config.content' => ['nullable', 'sometimes', 'string', 'max:20000'],
            'config.image' => ['nullable', 'sometimes', 'string', 'max:2048'],
            'config.image_mobile' => ['nullable', 'sometimes', 'string', 'max:2048'],
            'config.alt' => ['nullable', 'sometimes', 'string', 'max:255'],
            'config.link_url' => ['nullable', 'sometimes', 'string', 'max:1024'],
            'config.eyebrow' => ['nullable', 'sometimes', 'string', 'max:255'],
            'config.heading' => ['nullable', 'sometimes', 'string', 'max:255'],
            'config.handle' => ['nullable', 'sometimes', 'string', 'max:255'],
            'config.avatar' => ['nullable', 'sometimes', 'string', 'max:2048'],
            'config.video' => ['nullable', 'sometimes', 'string', 'max:2048'],
            'config.video_mobile' => ['nullable', 'sometimes', 'string', 'max:2048'],
            'config.poster' => ['nullable', 'sometimes', 'string', 'max:2048'],
            'config.images' => ['nullable', 'sometimes', 'array', 'max:8'],
            'config.images.*' => ['nullable', 'string', 'max:2048'],
            'config.button_text' => ['nullable', 'sometimes', 'string', 'max:255'],
            'config.button_url' => ['nullable', 'sometimes', 'string', 'max:1024'],
            'config.media_position' => ['nullable', 'sometimes', 'string', 'in:left,right'],
            'config.bullets' => ['sometimes', 'nullable', 'array'],
            'config.bullets.*' => ['nullable', 'string', 'max:255'],
            'config.primary_button_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'config.primary_button_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'config.secondary_button_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'config.secondary_button_url' => ['sometimes', 'nullable', 'string', 'max:255'],

            'config.stats' => ['sometimes', 'array'],
            'config.stats.*.value' => ['sometimes', 'string', 'max:64'],
            'config.stats.*.label' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
