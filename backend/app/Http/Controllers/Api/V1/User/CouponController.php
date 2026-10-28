<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidateCouponRequest;
use App\Http\Resources\V1\CouponResource;
use App\Services\Cart\CartService;
use App\Services\Coupon\CouponException;
use App\Services\Pricing\PricingService;
use Illuminate\Http\JsonResponse;

class CouponController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected PricingService $pricingService,
    ) {}

    public function validate(ValidateCouponRequest $request): JsonResponse
    {
        try {
            $cart = $this->cartService->validateAndRefresh($request->user());
            $breakdown = $this->pricingService->breakdown($cart, $request->string('code'));

            return $this->success(__('Coupon applied successfully.'), [
                'discount' => $breakdown['coupon_discount'],
                'grand_total' => $breakdown['grand_total'],
                'payable' => $breakdown['payable'],
                'coupon' => $breakdown['coupon'] ? new CouponResource($breakdown['coupon']) : null,
                'vendor_groups' => $breakdown['vendor_groups'],
            ]);
        } catch (CouponException $e) {
            return $this->error($e->getMessage(), [['code' => $e->code()]], 422);
        }
    }
}
