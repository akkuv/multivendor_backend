<?php

namespace Database\Factories;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VendorProfile>
 */
class VendorProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $storeName = fake()->unique()->company();

        return [
            'user_id' => User::factory()->vendor(),
            'store_name' => $storeName,
            'slug' => Str::slug($storeName),
            'description' => fake()->paragraph(),
            'logo' => null,
            'banner' => null,
            'address_line1' => fake()->streetAddress(),
            'address_line2' => fake()->secondaryAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => 'India',
            'pincode' => fake()->numerify('######'),
            'gstin' => strtoupper(fake()->bothify('??#########?#')),
            'commission_rate' => 10.00,
            'shipping_charge' => 50.00,
            'free_shipping_threshold' => null,
            'is_approved' => true,
            'status' => VendorStatus::Approved->value,
        ];
    }

    public function freeShipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'shipping_charge' => 0.00,
            'free_shipping_threshold' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => false,
            'status' => VendorStatus::Pending->value,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => true,
            'status' => VendorStatus::Approved->value,
        ]);
    }
}
