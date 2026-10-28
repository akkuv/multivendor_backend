<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 100, 5000);
        $mrp = $price * 1.25;

        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->bothify('V-####-????')),
            'barcode' => fake()->ean13(),
            'price' => $price,
            'mrp' => $mrp,
            'cost_price' => $price * 0.7,
            'discount' => 0,
            'stock' => fake()->numberBetween(1, 100),
            'reserved_stock' => 0,
            'weight' => fake()->randomFloat(2, 0.1, 5),
            'length' => fake()->randomFloat(2, 5, 50),
            'width' => fake()->randomFloat(2, 5, 50),
            'height' => fake()->randomFloat(2, 5, 50),
            'status' => 'ACTIVE',
            'attributes' => [],
            'combination_hash' => Str::random(24),
            'images' => [],
        ];
    }

    public function forOption(string $name, string $value): static
    {
        return $this->state(fn (array $attributes) => [
            'attributes' => [$name => $value],
            'combination_hash' => sha1($name.'='.$value),
        ]);
    }
}
