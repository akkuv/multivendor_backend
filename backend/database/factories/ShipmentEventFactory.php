<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentEvent>
 */
class ShipmentEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * `shipment_events` is unique on (shipment_id, status, occurred_at), so each
     * generated event needs its own timestamp to allow `count()` sequences.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement([
            ShipmentStatus::PickedUp,
            ShipmentStatus::InTransit,
            ShipmentStatus::OutForDelivery,
        ]);

        return [
            'shipment_id' => Shipment::factory(),
            'status' => $status->value,
            'location' => fake()->city(),
            'description' => $status->label(),
            'is_webhook' => true,
            'payload' => [],
            'occurred_at' => fake()->unique()->dateTimeBetween('-5 days', 'now'),
        ];
    }

    /**
     * A scan reported by the courier's own tracking feed rather than a webhook.
     */
    public function fromCourierFeed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_webhook' => false,
        ]);
    }
}
