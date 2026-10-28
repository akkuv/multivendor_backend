<?php

namespace Database\Factories;

use App\Enums\HomepageSectionType;
use App\Models\CategoryPage;
use App\Models\CategoryPageSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryPageSection>
 */
class CategoryPageSectionFactory extends Factory
{
    protected $model = CategoryPageSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_page_id' => CategoryPage::factory(),
            'type' => HomepageSectionType::BestSellers->value,
            'title' => fake()->sentence(3),
            'subtitle' => fake()->optional()->sentence(),
            'config' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /** @return Factory<CategoryPageSection> */
    public function ofType(string $type): Factory
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}
