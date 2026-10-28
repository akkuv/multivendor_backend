<?php

namespace Database\Factories;

use App\Enums\CommissionLevel;
use App\Enums\CommissionRuleType;
use App\Models\CommissionRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionRule>
 */
class CommissionRuleFactory extends Factory
{
    protected $model = CommissionRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'level' => CommissionLevel::Global->value,
            'scope_id' => null,
            'name' => fake()->unique()->word(),
            'rate' => fake()->randomFloat(2, 0, 30),
            'type' => CommissionRuleType::Percentage->value,
            'is_active' => true,
        ];
    }

    /** @return Factory<CommissionRule> */
    public function global(float $rate): Factory
    {
        return $this->state(fn (): array => [
            'level' => CommissionLevel::Global->value,
            'scope_id' => null,
            'rate' => $rate,
        ]);
    }

    /** @return Factory<CommissionRule> */
    public function category(int $categoryId, float $rate): Factory
    {
        return $this->state(fn (): array => [
            'level' => CommissionLevel::Category->value,
            'scope_id' => $categoryId,
            'rate' => $rate,
        ]);
    }

    /** @return Factory<CommissionRule> */
    public function vendor(int $vendorId, float $rate): Factory
    {
        return $this->state(fn (): array => [
            'level' => CommissionLevel::Vendor->value,
            'scope_id' => $vendorId,
            'rate' => $rate,
        ]);
    }

    /** @return Factory<CommissionRule> */
    public function product(int $productId, float $rate): Factory
    {
        return $this->state(fn (): array => [
            'level' => CommissionLevel::Product->value,
            'scope_id' => $productId,
            'rate' => $rate,
        ]);
    }
}
