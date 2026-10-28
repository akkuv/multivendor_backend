<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreColorRequest;
use App\Http\Requests\UpdateColorRequest;
use App\Http\Resources\V1\ColorResource;
use App\Models\Color;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ColorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $colors = Color::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(__('Colors fetched successfully.'), $colors, fn ($items) => ColorResource::collection($items));
    }

    public function store(StoreColorRequest $request): JsonResponse
    {
        $color = Color::create($request->validated());

        return $this->success(__('Color created successfully.'), new ColorResource($color), 201);
    }

    public function show(Color $color): JsonResponse
    {
        return $this->success(__('Color fetched successfully.'), new ColorResource($color));
    }

    public function update(UpdateColorRequest $request, Color $color): JsonResponse
    {
        $color->update($request->validated());

        return $this->success(__('Color updated successfully.'), new ColorResource($color->fresh()));
    }

    public function destroy(Color $color): JsonResponse
    {
        $color->delete();

        return $this->success(__('Color deleted successfully.'));
    }

    public function toggleActive(Color $color): JsonResponse
    {
        $color->update(['is_active' => ! $color->is_active]);

        return $this->success(__('Color status updated.'), new ColorResource($color->fresh()));
    }
}
