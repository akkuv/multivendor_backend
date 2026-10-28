<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $testimonials = Testimonial::query()
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', (bool) $request->input('is_active')))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate((int) ($request->input('per_page', 15)));

        return $this->paginated(__('Testimonials fetched successfully.'), $testimonials, fn ($items) => $items->values());
    }

    public function store(Request $request): JsonResponse
    {
        $testimonial = Testimonial::create($this->validated($request));

        return $this->success(__('Testimonial created successfully.'), $testimonial, 201);
    }

    public function show(Testimonial $testimonial): JsonResponse
    {
        return $this->success(__('Testimonial fetched successfully.'), $testimonial);
    }

    public function update(Request $request, Testimonial $testimonial): JsonResponse
    {
        $testimonial->update($this->validated($request));

        return $this->success(__('Testimonial updated successfully.'), $testimonial->fresh());
    }

    public function destroy(Testimonial $testimonial): JsonResponse
    {
        $testimonial->delete();

        return $this->success(__('Testimonial deleted successfully.'));
    }

    public function toggle(Testimonial $testimonial): JsonResponse
    {
        $testimonial->update(['is_active' => ! $testimonial->is_active]);

        return $this->success(__('Testimonial status updated.'), $testimonial->fresh());
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'author_name' => ['required', 'string', 'max:150'],
            'author_role' => ['sometimes', 'nullable', 'string', 'max:150'],
            'author_avatar' => ['sometimes', 'nullable', 'string'],
            'image' => ['sometimes', 'nullable', 'string'],
            'url' => ['sometimes', 'nullable', 'string'],
            'content' => ['required', 'string'],
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
