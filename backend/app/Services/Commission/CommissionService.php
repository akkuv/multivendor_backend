<?php

namespace App\Services\Commission;

use App\Enums\CommissionLevel;
use App\Enums\CommissionRuleType;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\CommissionSetting;
use App\Models\Product;
use App\Models\VendorProfile;

class CommissionService
{
    /**
     * Resolve the commission rule that applies to a product from a vendor,
     * following the priority: Product -> Vendor rule -> Vendor override ->
     * Category -> Global.
     *
     * A vendor's profile commission_rate is auto-assigned at registration from
     * config('commission.default_rate'); it is only treated as an explicit
     * vendor-level override when it differs from that automatic default.
     *
     * @return array{rate: float, level: CommissionLevel, rule_id: ?int}
     */
    public function ruleFor(VendorProfile $vendor, Product $product): array
    {
        $productRule = CommissionRule::query()
            ->where('level', CommissionLevel::Product->value)
            ->where('scope_id', $product->id)
            ->where('is_active', true)
            ->first();

        if ($productRule) {
            return $this->result($productRule, CommissionLevel::Product, (float) $productRule->rate);
        }

        $vendorRule = CommissionRule::query()
            ->where('level', CommissionLevel::Vendor->value)
            ->where('scope_id', $vendor->id)
            ->where('is_active', true)
            ->first();

        if ($vendorRule) {
            return $this->result($vendorRule, CommissionLevel::Vendor, (float) $vendorRule->rate);
        }

        if ($this->hasExplicitVendorRate($vendor)) {
            return [
                'rate' => (float) $vendor->commission_rate,
                'level' => CommissionLevel::Vendor,
                'rule_id' => null,
            ];
        }

        $categoryRule = $this->categoryRuleFor($product);

        if ($categoryRule) {
            return $this->result($categoryRule, CommissionLevel::Category, (float) $categoryRule->rate);
        }

        $globalRule = CommissionRule::query()
            ->where('level', CommissionLevel::Global->value)
            ->whereNull('scope_id')
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($globalRule) {
            return $this->result($globalRule, CommissionLevel::Global, (float) $globalRule->rate);
        }

        $globalSetting = CommissionSetting::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        return [
            'rate' => (float) ($globalSetting->rate ?? config('commission.default_rate', 10)),
            'level' => CommissionLevel::Global,
            'rule_id' => null,
        ];
    }

    /**
     * Apply the resolved rule to an amount and return the marketplace portion
     * and the net payable to the vendor.
     *
     * @return array{rate: float, commission_amount: float, net_amount: float, level: string, rule_id: ?int}
     */
    public function calculateFor(VendorProfile $vendor, Product $product, float $amount): array
    {
        $resolution = $this->ruleFor($vendor, $product);

        if ($resolution['rule_id'] !== null) {
            $rule = CommissionRule::find($resolution['rule_id']);
            $commission = $rule && $rule->type === CommissionRuleType::Fixed
                ? round((float) $rule->rate, 2)
                : round($amount * ($resolution['rate'] / 100), 2);
        } else {
            $commission = round($amount * ($resolution['rate'] / 100), 2);
        }

        return [
            'rate' => $resolution['rate'],
            'commission_amount' => $commission,
            'net_amount' => round($amount - $commission, 2),
            'level' => $resolution['level']->value,
            'rule_id' => $resolution['rule_id'],
        ];
    }

    /**
     * Vendor-specific rate used wherever a single flat rate is enough (e.g.
     * the vendor's own commission override, then the global fallback).
     */
    public function commissionRateFor(VendorProfile $vendor): float
    {
        if ($this->hasExplicitVendorRate($vendor)) {
            return (float) $vendor->commission_rate;
        }

        $global = CommissionSetting::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        return (float) ($global->rate ?? config('commission.default_rate', 10));
    }

    /**
     * Whether the vendor's profile commission_rate is an explicit override
     * rather than the automatic default populated at registration.
     */
    protected function hasExplicitVendorRate(VendorProfile $vendor): bool
    {
        $rate = (float) $vendor->commission_rate;

        return $rate > 0 && $rate !== (float) config('commission.default_rate', 10);
    }

    protected function categoryRuleFor(Product $product): ?CommissionRule
    {
        $candidates = $this->categoryChain($product);

        if ($candidates === []) {
            return null;
        }

        foreach ($candidates as $scopeId) {
            $rule = CommissionRule::query()
                ->where('level', CommissionLevel::Category->value)
                ->where('scope_id', $scopeId)
                ->where('is_active', true)
                ->first();

            if ($rule) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Product category plus its ancestors, ordered closest first.
     *
     * @return array<int, int>
     */
    protected function categoryChain(Product $product): array
    {
        $start = $product->subcategory_id ?: $product->category_id;

        if ($start === null) {
            return [];
        }

        $ids = [];
        $cursor = $start;

        while ($cursor !== null && ! in_array($cursor, $ids, true)) {
            $ids[] = (int) $cursor;

            /** @var Category|null $category */
            $category = Category::query()->whereKey($cursor)->first(['parent_id']);

            $cursor = $category?->parent_id;
        }

        return $ids;
    }

    /**
     * @return array{rate: float, level: CommissionLevel, rule_id: ?int}
     */
    protected function result(CommissionRule $rule, CommissionLevel $level, float $rate): array
    {
        return [
            'rate' => $rate,
            'level' => $level,
            'rule_id' => $rule->id,
        ];
    }
}
