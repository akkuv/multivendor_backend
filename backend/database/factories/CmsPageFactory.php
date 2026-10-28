<?php

namespace Database\Factories;

use App\Enums\CmsPageType;
use App\Models\CmsPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CmsPage>
 */
class CmsPageFactory extends Factory
{
    protected $model = CmsPage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => CmsPageType::Page->value,
            'slug' => fake()->unique()->slug(),
            'title' => fake()->sentence(3),
            'icon' => null,
            'content' => fake()->paragraphs(3, true),
            'meta_title' => null,
            'meta_description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /** @return Factory<CmsPage> */
    public function faq(): Factory
    {
        return $this->state(fn (): array => [
            'type' => CmsPageType::Faq->value,
            'icon' => 'help-circle',
        ]);
    }
}
