<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSizeRequest;
use App\Http\Requests\UpdateSizeRequest;
use App\Http\Resources\V1\SizeResource;
use App\Models\Size;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SizeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sizes = Size::query()
            ->with('group')
            ->when($request->filled('size_group_id'), fn ($q) => $q->where('size_group_id', $request->integer('size_group_id')))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(__('Sizes fetched successfully.'), $sizes, fn ($items) => SizeResource::collection($items));
    }

    public function store(StoreSizeRequest $request): JsonResponse
    {
        $size = Size::create($request->validated());

        return $this->success(__('Size created successfully.'), new SizeResource($size), 201);
    }

    public function show(Size $size): JsonResponse
    {
        return $this->success(__('Size fetched successfully.'), new SizeResource($size->load('group')));
    }

    public function update(UpdateSizeRequest $request, Size $size): JsonResponse
    {
        $size->update($request->validated());

        return $this->success(__('Size updated successfully.'), new SizeResource($size->fresh(['group'])));
    }

    public function destroy(Size $size): JsonResponse
    {
        $size->delete();

        return $this->success(__('Size deleted successfully.'));
    }

    public function toggleActive(Size $size): JsonResponse
    {
        $size->update(['is_active' => ! $size->is_active]);

        return $this->success(__('Size status updated.'), new SizeResource($size->fresh(['group'])));
    }
}
