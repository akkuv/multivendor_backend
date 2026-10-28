<?php

namespace Database\Factories;

use App\Enums\ShipmentPickupStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'Shiprocket',
            'tracking_number' => fake()->numerify('SHIP##########'),
            'tracking_url' => 'https://shiprocket.co/tracking/'.fake()->bothify('SHIP##########'),
            'status' => ShipmentStatus::Pending->value,
            'charges' => fake()->randomFloat(2, 40, 200),
            'weight' => fake()->randomFloat(2, 0.5, 10),
            'courier_name' => null,
            'payload' => [],
            'shipped_at' => null,
            'delivered_at' => null,
        ];
    }

    /**
     * A shipment that already exists on Shiprocket and has a label available.
     */
    public function onShiprocket(): static
    {
        return $this->state(fn (array $attributes): array => [
            'shiprocket_order_id' => fake()->numberBetween(1_000_000, 9_999_999),
            'shipment_id' => fake()->numberBetween(10_000_000, 99_999_999),
            'courier_id' => fake()->numberBetween(1, 60),
            'courier_name' => fake()->randomElement(['Delhivery', 'Blue Dart', 'Ecom Express', 'XpressBees']),
            'label_url' => 'https://app.shiprocket.in/shipments/label/'.fake()->bothify('?token=########'),
            'pickup_status' => ShipmentPickupStatus::None->value,
        ]);
    }

    public function awbGenerated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ShipmentStatus::AwbGenerated->value,
            'shipped_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ShipmentStatus::Delivered->value,
            'shipped_at' => now()->subDays(4),
            'delivered_at' => now()->subDay(),
            'pickup_status' => ShipmentPickupStatus::PickedUp->value,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ShipmentStatus::Failed->value,
            'pickup_status' => ShipmentPickupStatus::Failed->value,
            'error_message' => 'Courier assignment rejected by Shiprocket.',
        ]);
    }
}
