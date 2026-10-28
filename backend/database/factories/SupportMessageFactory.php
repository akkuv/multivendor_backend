<?php

namespace Database\Factories;

use App\Enums\SupportSenderRole;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportMessage>
 */
class SupportMessageFactory extends Factory
{
    protected $model = SupportMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_conversation_id' => SupportConversation::factory(),
            'sender_user_id' => User::factory(),
            'sender_role' => SupportSenderRole::Vendor->value,
            'body' => fake()->sentence(),
        ];
    }

    public function fromCustomer(?User $customer = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'sender_user_id' => $customer?->id ?? User::factory(),
            'sender_role' => SupportSenderRole::Customer->value,
        ]);
    }

    public function fromAdmin(?User $admin = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'sender_user_id' => $admin?->id ?? User::factory(),
            'sender_role' => SupportSenderRole::Admin->value,
        ]);
    }

    public function fromBot(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sender_user_id' => null,
            'sender_role' => SupportSenderRole::Bot->value,
            'is_bot' => true,
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes): array => [
            'read_at' => now(),
        ]);
    }
}
