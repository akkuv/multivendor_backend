<?php

namespace Database\Factories;

use App\Enums\SupportRequesterType;
use App\Models\SupportConversation;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportConversation>
 */
class SupportConversationFactory extends Factory
{
    protected $model = SupportConversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_type' => SupportRequesterType::Vendor,
            'vendor_id' => VendorProfile::factory(),
            'vendor_user_id' => User::factory(),
            'subject' => null,
        ];
    }

    public function forCustomer(User $customer): static
    {
        return $this->state(fn (array $attributes): array => [
            'requester_type' => SupportRequesterType::Customer,
            'vendor_id' => null,
            'vendor_user_id' => null,
            'customer_user_id' => $customer->id,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_closed' => true,
            'closed_at' => now(),
        ]);
    }

    /**
     * The requester spoke most recently, so the conversation is waiting on an admin.
     */
    public function awaitingAdmin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'last_requester_reply_at' => now()->subMinutes(5),
            'last_admin_reply_at' => now()->subHour(),
        ]);
    }
}
