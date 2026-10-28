<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttributeValueRequest;
use App\Http\Requests\UpdateAttributeValueRequest;
use App\Http\Resources\V1\AttributeValueResource;
use App\Models\AttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttributeValueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $attributeValues = AttributeValue::query()
            ->with('attribute')
            ->when($request->filled('attribute_id'), fn ($q) => $q->where('attribute_id', $request->integer('attribute_id')))
            ->when($request->filled('search'), fn ($q) => $q->where('value', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(__('Attribute values fetched successfully.'), $attributeValues, fn ($items) => AttributeValueResource::collection($items));
    }

    public function store(StoreAttributeValueRequest $request): JsonResponse
    {
        $attributeValue = AttributeValue::create($request->validated());

        return $this->success(__('Attribute value created successfully.'), new AttributeValueResource($attributeValue), 201);
    }

    public function show(AttributeValue $attributeValue): JsonResponse
    {
        $attributeValue->load('attribute');

        return $this->success(__('Attribute value fetched successfully.'), new AttributeValueResource($attributeValue));
    }

    public function update(UpdateAttributeValueRequest $request, AttributeValue $attributeValue): JsonResponse
    {
        $attributeValue->update($request->validated());

        return $this->success(__('Attribute value updated successfully.'), new AttributeValueResource($attributeValue->fresh(['attribute'])));
    }

    public function destroy(AttributeValue $attributeValue): JsonResponse
    {
        $attributeValue->delete();

        return $this->success(__('Attribute value deleted successfully.'));
    }

    public function toggleActive(AttributeValue $attributeValue): JsonResponse
    {
        $attributeValue->update(['is_active' => ! $attributeValue->is_active]);

        return $this->success(__('Attribute value status updated.'), new AttributeValueResource($attributeValue->fresh(['attribute'])));
    }
}
