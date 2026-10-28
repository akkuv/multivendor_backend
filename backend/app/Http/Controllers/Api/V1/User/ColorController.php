<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ColorResource;
use App\Models\Color;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ColorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $colors = Color::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->success(__('Colors fetched successfully.'), ColorResource::collection($colors));
    }
}
