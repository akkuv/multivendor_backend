<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $price = fake()->randomFloat(2, 100, 5000);
        $mrp = $price * 1.25;

        return [
            'vendor_id' => VendorProfile::factory(),
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'subcategory_id' => null,
            'name' => ucwords($name),
            'slug' => Str::slug($name).'-'.Str::random(6),
            'description' => fake()->paragraphs(2, true),
            'short_description' => fake()->sentence(),
            'specifications' => [
                'Material' => fake()->word(),
                'Warranty' => fake()->randomElement(['1 year', '6 months', '2 years']),
            ],
            'tax_class' => 'standard',
            'gst' => fake()->randomElement([5, 12, 18, 28]),
            'hsn_code' => fake()->numerify('####'),
            'price' => $price,
            'mrp' => $mrp,
            'stock' => fake()->numberBetween(1, 100),
            'reserved_stock' => 0,
            'sku' => strtoupper(fake()->bothify('SKU-####-????')),
            'images' => fake()->randomElements([
                'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?ixlib=rb-4.0.0&auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1556228453-efd6c1ff04f6?ixlib=rb-4.0.0&auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?ixlib=rb-4.0.0&auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?ixlib=rb-4.0.0&auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1580910051074-3eb694886505?ixlib=rb-4.0.0&auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1546868871-7041f2a55e12?ixlib=rb-4.0.0&auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1523275335684-37898b6baf30?ixlib=rb-4.0.0&auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1572635196237-14b3f281503f?ixlib=rb-4.0.0&auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1526947425960-945c6e72858f?ixlib=rb-4.0.0&auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1596462502278-27bfdc403348?ixlib=rb-4.0.0&auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1585386959984-a4155224a1ad?ixlib=rb-4.0.0&auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?ixlib=rb-4.0.0&auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1587731556938-38755b4803a6?ixlib=rb-4.0.0&auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1610890716171-6b1bb98ffd09?ixlib=rb-4.0.0&auto=format&fit=crop&w=900&q=80',
            ], fake()->numberBetween(1, 3)),
            'attributes' => [],
            'is_active' => true,
            'status' => 'PUBLISHED',
            'approval_status' => 'APPROVED',
            'featured' => false,
            'best_seller' => false,
            'is_returnable' => true,
            'return_days' => fake()->randomElement([7, 10, 15, 30]),
            'return_policy' => fake()->randomElement([
                'Return a product within 7 days of delivery for a full refund.',
                'Return a product within 30 days of delivery for a full refund. Items must be unused and in original packaging.',
                'Returns accepted within 10 days. Refunds issued after item inspection.',
            ]),
            'created_by' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => 'PENDING_APPROVAL',
            'status' => 'DRAFT',
            'approved_at' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => 'REJECTED',
            'status' => 'DRAFT',
            'rejected_at' => now(),
            'rejection_reason' => 'Does not meet listing guidelines.',
        ]);
    }
}
