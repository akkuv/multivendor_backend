<?php

namespace Database\Factories;

use App\Models\ProductAnswer;
use App\Models\ProductQuestion;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAnswer>
 */
class ProductAnswerFactory extends Factory
{
    protected $model = ProductAnswer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_question_id' => ProductQuestion::factory(),
            'vendor_id' => VendorProfile::factory(),
            'answer' => fake()->paragraph(),
        ];
    }
}
