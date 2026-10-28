<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderAddress>
 */
class OrderAddressFactory extends Factory
{
    protected $model = OrderAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'type' => 'shipping',
            'name' => fake()->name(),
            'phone' => fake()->numerify('##########'),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => fake()->secondaryAddress(),
            'landmark' => fake()->optional()->word(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => 'India',
            'pincode' => fake()->numerify('######'),
        ];
    }
}
