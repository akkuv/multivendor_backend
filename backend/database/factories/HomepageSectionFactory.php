<?php

namespace Database\Factories;

use App\Enums\HomepageSectionType;
use App\Models\HomepageSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomepageSection>
 */
class HomepageSectionFactory extends Factory
{
    protected $model = HomepageSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => HomepageSectionType::BestSellers->value,
            'title' => fake()->sentence(3),
            'subtitle' => fake()->optional()->sentence(),
            'config' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /** @return Factory<HomepageSection> */
    public function ofType(string $type): Factory
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}
