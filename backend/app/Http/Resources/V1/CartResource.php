<?php

namespace App\Http\Resources\V1;

use App\Services\Pricing\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $breakdown = app(PricingService::class)->breakdown($this->resource);
        $breakdownGroups = collect($breakdown['vendor_groups'])->keyBy('vendor_id');

        $vendorGroups = $this->items
            ->groupBy(fn ($item) => $item->product?->vendor_id ?? 0)
            ->map(function ($group, $vendorId) use ($breakdownGroups): array {
                $vendor = $group->first()->product?->vendor;
                $groupTotal = $breakdownGroups->get((int) $vendorId, []);

                return [
                    'vendor_id' => (int) $vendorId,
                    'vendor' => $vendor ? new StorefrontVendorResource($vendor) : null,
                    'items' => CartItemResource::collection($group->values()),
                    'item_count' => $group->sum('quantity'),
                    'subtotal' => $groupTotal['subtotal'] ?? 0.0,
                    'discount' => $groupTotal['discount'] ?? 0.0,
                    'tax' => $groupTotal['tax'] ?? 0.0,
                    'fees_total' => $groupTotal['fees_total'] ?? 0.0,
                    'shipping' => $groupTotal['shipping'] ?? 0.0,
                    'coupon_discount' => $groupTotal['coupon_discount'] ?? 0.0,
                    'cod_charge' => $groupTotal['cod_charge'] ?? 0.0,
                    'total' => $groupTotal['total'] ?? 0.0,
                ];
            })
            ->values();

        return [
            'id' => $this->id,
            'vendor_groups' => $vendorGroups,
            'items' => CartItemResource::collection($this->items),
            'item_count' => $this->items->sum('quantity'),
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
            'messages' => $this->additional['messages'] ?? [],
        ];
    }
}
