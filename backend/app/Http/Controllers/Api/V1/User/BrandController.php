<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $brands = Brand::query()
            ->where('is_active', true)
            ->when($request->filled('category_id'), fn ($q) => $q->whereHas('categories', fn ($c) => $c->where('categories.id', $request->integer('category_id'))))
            ->with('categories')
            ->withCount(['products' => fn ($q) => $q->live()])
            ->orderBy('name')
            ->get();

        return $this->success(__('Brands fetched successfully.'), BrandResource::collection($brands));
    }
}
