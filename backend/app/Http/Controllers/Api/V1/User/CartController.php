<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\V1\CartResource;
use App\Models\CartItem;
use App\Services\Cart\CartService;
use App\Services\Cart\InvalidCartException;
use App\Services\Pricing\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected PricingService $pricingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $cart = $this->cartService->validateAndRefresh($request->user());

        return $this->success(
            'Cart fetched successfully.',
            CartResource::make($cart)->additional(['messages' => $this->cartService->messages()]),
        );
    }

    public function add(AddCartItemRequest $request): JsonResponse
    {
        try {
            $cart = $this->cartService->addItem(
                $request->user(),
                $request->integer('product_id'),
                $request->input('variant_id') !== null ? $request->integer('variant_id') : null,
                $request->integer('quantity', 1),
            );

            return $this->success(__('Item added to cart.'), new CartResource($cart));
        } catch (InvalidCartException $e) {
            return $this->error($e->getMessage(), null, 422);
        }
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): JsonResponse
    {
        try {
            $cart = $this->cartService->updateQuantity(
                $request->user(),
                $cartItem->id,
                $request->integer('quantity'),
            );

            return $this->success(__('Cart updated.'), new CartResource($cart));
        } catch (InvalidCartException $e) {
            return $this->error($e->getMessage(), null, 422);
        }
    }

    public function remove(Request $request, CartItem $cartItem): JsonResponse
    {
        $cart = $this->cartService->removeItem($request->user(), $cartItem->id);

        return $this->success(__('Item removed from cart.'), new CartResource($cart));
    }

    public function clear(Request $request): JsonResponse
    {
        $this->cartService->clear($request->user());

        return $this->success(__('Cart cleared.'));
    }

    public function totals(Request $request): JsonResponse
    {
        $cart = $this->cartService->validateAndRefresh($request->user());

        $breakdown = $this->pricingService->breakdown($cart);

        return $this->success(__('Cart totals.'), [
            'item_count' => $cart->items->sum('quantity'),
            'subtotal' => $breakdown['subtotal'],
            'discount' => $breakdown['discount'],
            'coupon_discount' => $breakdown['coupon_discount'],
            'tax' => $breakdown['tax'],
            'fees_total' => $breakdown['fees_total'],
            'shipping' => $breakdown['shipping'],
            'cod_charge' => $breakdown['cod_charge'],
            'wallet_amount' => $breakdown['wallet_amount'],
            'grand_total' => $breakdown['grand_total'],
            'total' => $breakdown['payable'],
            'savings' => $breakdown['savings'],
        ]);
    }
}
