<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Http\Resources\V1\CouponResource;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $coupons = Coupon::query()
            ->with('vendor')
            ->when($request->filled('search'), fn ($q) => $q->where('code', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(__('Coupons fetched successfully.'), $coupons, fn ($items) => CouponResource::collection($items));
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = Coupon::create($request->safe()->except(['product_ids', 'category_ids', 'brand_ids', 'vendor_ids']));
        $this->syncRestrictions($coupon, $request);

        return $this->success(__('Coupon created successfully.'), new CouponResource($coupon->fresh()->load('vendor', 'products', 'categories', 'brands', 'vendors')), 201);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        return $this->success(__('Coupon fetched successfully.'), new CouponResource($coupon->load('vendor', 'products', 'categories', 'brands', 'vendors')));
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $coupon->update($request->safe()->except(['product_ids', 'category_ids', 'brand_ids', 'vendor_ids']));
        $this->syncRestrictions($coupon, $request);

        return $this->success(__('Coupon updated successfully.'), new CouponResource($coupon->fresh()->load('vendor', 'products', 'categories', 'brands', 'vendors')));
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return $this->success(__('Coupon deleted successfully.'));
    }

    public function toggle(Coupon $coupon): JsonResponse
    {
        $coupon->forceFill(['is_active' => ! $coupon->is_active])->save();

        return $this->success($coupon->is_active ? 'Coupon enabled successfully.' : 'Coupon disabled successfully.', new CouponResource($coupon->fresh()));
    }

    public function usage(Coupon $coupon): JsonResponse
    {
        $stats = $coupon->usages();

        return $this->success(__('Coupon usage fetched successfully.'), [
            'coupon' => new CouponResource($coupon),
            'redeemed_count' => $stats->count(),
            'distinct_users' => (clone $stats)->distinct('user_id')->count('user_id'),
            'total_discount' => round((float) $coupon->usages()->sum('discount_amount'), 2),
            'usage_limit' => $coupon->usage_limit,
            'per_user_limit' => $coupon->per_user_limit,
            'remaining' => $coupon->usage_limit !== null ? max(0, (int) $coupon->usage_limit - (int) $coupon->used_count) : null,
            'first_redeemed_at' => $coupon->usages()->oldest()->value('created_at'),
            'latest_redeemed_at' => $coupon->usages()->latest()->value('created_at'),
        ]);
    }

    public function history(Request $request, Coupon $coupon): JsonResponse
    {
        $usages = $coupon->usages()
            ->with('user', 'order')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(__('Coupon redemption history fetched successfully.'), $usages, fn ($items) => $items->map(fn ($usage) => [
            'id' => $usage->id,
            'discount_amount' => (float) $usage->discount_amount,
            'user' => $usage->user ? ['id' => $usage->user->id, 'name' => $usage->user->name, 'email' => $usage->user->email] : null,
            'order_number' => $usage->order?->order_number,
            'created_at' => $usage->created_at,
        ]));
    }

    protected function syncRestrictions(Coupon $coupon, Request $request): void
    {
        $map = [
            'products' => 'product_ids',
            'categories' => 'category_ids',
            'brands' => 'brand_ids',
            'vendors' => 'vendor_ids',
        ];

        foreach ($map as $relation => $inputKey) {
            if ($request->has($inputKey)) {
                $coupon->$relation()->sync(array_map('intval', (array) $request->input($inputKey)));
            }
        }
    }
}
