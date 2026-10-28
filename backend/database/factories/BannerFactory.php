<?php

namespace Database\Factories;

use App\Enums\BannerPosition;
use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    protected $model = Banner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'subtitle' => fake()->sentence(),
            'image' => 'banners/'.fake()->uuid().'.jpg',
            'mobile_image' => null,
            'button_text' => 'Shop Now',
            'url' => fake()->url(),
            'start_date' => null,
            'end_date' => null,
            'position' => BannerPosition::Hero->value,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /** @return Factory<Banner> */
    public function hero(): Factory
    {
        return $this->state(fn (): array => ['position' => BannerPosition::Hero->value]);
    }
}
