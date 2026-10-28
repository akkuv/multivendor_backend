<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CollectionResource;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $collections = Collection::query()
            ->where('is_active', true)
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->string('gender')))
            ->withCount(['products' => fn ($q) => $q->live()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->success(__('Collections fetched successfully.'), CollectionResource::collection($collections));
    }

    public function show(Collection $collection): JsonResponse
    {
        if (! $collection->is_active) {
            return $this->error(__('Collection not found.'), null, 404);
        }

        $collection->loadCount(['products' => fn ($q) => $q->live()]);

        return $this->success(__('Collection fetched successfully.'), new CollectionResource($collection));
    }
}
