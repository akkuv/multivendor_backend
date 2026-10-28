<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\BannerPosition;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $banners = Banner::query()
            ->when($request->filled('position'), fn ($q) => $q->where('position', $request->input('position')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', (bool) $request->input('is_active')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');

                return $q->where(fn ($w) => $w->where('title', 'like', "%{$search}%")->orWhere('subtitle', 'like', "%{$search}%"));
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate((int) ($request->input('per_page', 15)));

        return $this->paginated(__('Banners fetched successfully.'), $banners, fn ($items) => $items->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $banner = Banner::create($data);

        return $this->success(__('Banner created successfully.'), $banner, 201);
    }

    public function show(Banner $banner): JsonResponse
    {
        return $this->success(__('Banner fetched successfully.'), $banner);
    }

    public function update(Request $request, Banner $banner): JsonResponse
    {
        $banner->update($this->validated($request));

        return $this->success(__('Banner updated successfully.'), $banner->fresh());
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $banner->delete();

        return $this->success(__('Banner deleted successfully.'));
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer', 'exists:banners,id'],
        ]);

        foreach (array_values($data['ordered_ids']) as $index => $id) {
            Banner::query()->whereKey($id)->update(['sort_order' => $index]);
        }

        return $this->success(__('Banners reordered successfully.'));
    }

    public function toggle(Banner $banner): JsonResponse
    {
        $banner->update(['is_active' => ! $banner->is_active]);

        return $this->success(__('Banner status updated.'), $banner->fresh());
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'image' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'mobile_image' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'button_text' => ['sometimes', 'nullable', 'string', 'max:60'],
            'url' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false && ! str_starts_with($value, '/')) {
                        $fail('The :attribute must be a valid URL or path.');
                    }
                },
            ],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'position' => ['sometimes', 'in:'.implode(',', array_column(BannerPosition::cases(), 'value'))],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
