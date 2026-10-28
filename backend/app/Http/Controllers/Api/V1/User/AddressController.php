<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Http\Resources\V1\AddressResource;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->get();

        return $this->success(__('Addresses fetched successfully.'), AddressResource::collection($addresses));
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $isDefault = $request->boolean('is_default');

        if ($isDefault) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address = $request->user()->addresses()->create($request->validated());

        return $this->success(__('Address added successfully.'), new AddressResource($address), 201);
    }

    public function show(Request $request, Address $address): JsonResponse
    {
        $this->authorize('view', $address);

        return $this->success(__('Address fetched successfully.'), new AddressResource($address));
    }

    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        $this->authorize('update', $address);

        if ($request->boolean('is_default')) {
            $request->user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($request->validated());

        return $this->success(__('Address updated successfully.'), new AddressResource($address->fresh()));
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        $this->authorize('delete', $address);

        $address->delete();

        return $this->success(__('Address deleted successfully.'));
    }
}
