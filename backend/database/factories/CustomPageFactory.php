<?php

namespace Database\Factories;

use App\Models\CustomPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomPage>
 */
class CustomPageFactory extends Factory
{
    protected $model = CustomPage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($title),
            'title' => Str::title($title),
            'meta_title' => null,
            'meta_description' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
