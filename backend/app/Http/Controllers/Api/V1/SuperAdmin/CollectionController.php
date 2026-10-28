<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCollectionRequest;
use App\Http\Requests\UpdateCollectionRequest;
use App\Http\Resources\V1\CollectionResource;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $collections = Collection::query()
            ->withCount(['products' => fn ($q) => $q->live()])
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->string('gender')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(__('Collections fetched successfully.'), $collections, fn ($items) => CollectionResource::collection($items));
    }

    public function store(StoreCollectionRequest $request): JsonResponse
    {
        $collection = Collection::create($request->validated());
        $collection->products()->sync($request->input('product_ids', []));

        return $this->success(__('Collection created successfully.'), new CollectionResource($collection->load('products')), 201);
    }

    public function show(Collection $collection): JsonResponse
    {
        return $this->success(__('Collection fetched successfully.'), new CollectionResource($collection->load('products')));
    }

    public function update(UpdateCollectionRequest $request, Collection $collection): JsonResponse
    {
        $collection->update($request->validated());

        if ($request->has('product_ids')) {
            $collection->products()->sync($request->input('product_ids', []));
        }

        return $this->success(__('Collection updated successfully.'), new CollectionResource($collection->fresh(['products'])));
    }

    public function destroy(Collection $collection): JsonResponse
    {
        $collection->delete();

        return $this->success(__('Collection deleted successfully.'));
    }

    public function toggleActive(Collection $collection): JsonResponse
    {
        $collection->update(['is_active' => ! $collection->is_active]);

        return $this->success(__('Collection status updated.'), new CollectionResource($collection->fresh()));
    }
}
