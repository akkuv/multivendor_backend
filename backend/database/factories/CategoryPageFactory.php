<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\CategoryPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CategoryPage>
 */
class CategoryPageFactory extends Factory
{
    protected $model = CategoryPage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'slug' => Str::slug(fake()->unique()->words(2, true)),
            'title' => fake()->sentence(3),
            'subtitle' => fake()->optional()->sentence(),
            'description' => fake()->optional()->paragraph(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
