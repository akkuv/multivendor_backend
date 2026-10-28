<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSizeGroupRequest;
use App\Http\Requests\UpdateSizeGroupRequest;
use App\Http\Resources\V1\SizeGroupResource;
use App\Models\SizeGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SizeGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sizeGroups = SizeGroup::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->withCount('sizes')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(__('Size groups fetched successfully.'), $sizeGroups, fn ($items) => SizeGroupResource::collection($items));
    }

    public function store(StoreSizeGroupRequest $request): JsonResponse
    {
        $sizeGroup = SizeGroup::create($request->validated());

        return $this->success(__('Size group created successfully.'), new SizeGroupResource($sizeGroup), 201);
    }

    public function show(SizeGroup $sizeGroup): JsonResponse
    {
        $sizeGroup->load('sizes');

        return $this->success(__('Size group fetched successfully.'), new SizeGroupResource($sizeGroup));
    }

    public function update(UpdateSizeGroupRequest $request, SizeGroup $sizeGroup): JsonResponse
    {
        $sizeGroup->update($request->validated());

        return $this->success(__('Size group updated successfully.'), new SizeGroupResource($sizeGroup->fresh()));
    }

    public function destroy(SizeGroup $sizeGroup): JsonResponse
    {
        $sizeGroup->delete();

        return $this->success(__('Size group deleted successfully.'));
    }

    public function toggleActive(SizeGroup $sizeGroup): JsonResponse
    {
        $sizeGroup->update(['is_active' => ! $sizeGroup->is_active]);

        return $this->success(__('Size group status updated.'), new SizeGroupResource($sizeGroup->fresh()));
    }
}
