<?php

namespace Database\Factories;

use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryTransaction>
 */
class InventoryTransactionFactory extends Factory
{
    protected $model = InventoryTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 25);

        return [
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'type' => 'ADD',
            'quantity' => $qty,
            'stock_before' => 0,
            'stock_after' => $qty,
            'reason' => fake()->sentence(),
            'created_by' => null,
        ];
    }

    public function add(): static
    {
        return $this->state(['type' => 'ADD']);
    }

    public function damaged(): static
    {
        return $this->state(['type' => 'DAMAGED', 'quantity' => fake()->numberBetween(-10, -1)]);
    }
}
