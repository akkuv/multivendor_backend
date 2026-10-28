<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(),
            'name' => fake()->sentence(3),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraphs(2, true),
            'is_active' => true,
        ];
    }
}
