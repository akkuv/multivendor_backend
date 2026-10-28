<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttributeRequest;
use App\Http\Requests\UpdateAttributeRequest;
use App\Http\Resources\V1\AttributeResource;
use App\Models\Attribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttributeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $attributes = Attribute::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->withCount('values')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(__('Attributes fetched successfully.'), $attributes, fn ($items) => AttributeResource::collection($items));
    }

    public function store(StoreAttributeRequest $request): JsonResponse
    {
        $attribute = Attribute::create($request->validated());

        return $this->success(__('Attribute created successfully.'), new AttributeResource($attribute), 201);
    }

    public function show(Attribute $attribute): JsonResponse
    {
        $attribute->load('values');

        return $this->success(__('Attribute fetched successfully.'), new AttributeResource($attribute));
    }

    public function update(UpdateAttributeRequest $request, Attribute $attribute): JsonResponse
    {
        $attribute->update($request->validated());

        return $this->success(__('Attribute updated successfully.'), new AttributeResource($attribute->fresh()));
    }

    public function destroy(Attribute $attribute): JsonResponse
    {
        if ($attribute->values()->count() > 0) {
            return $this->error(__('Attribute has values assigned and cannot be deleted.'), null, 422);
        }

        $attribute->delete();

        return $this->success(__('Attribute deleted successfully.'));
    }

    public function toggleActive(Attribute $attribute): JsonResponse
    {
        $attribute->update(['is_active' => ! $attribute->is_active]);

        return $this->success(__('Attribute status updated.'), new AttributeResource($attribute->fresh()));
    }
}
