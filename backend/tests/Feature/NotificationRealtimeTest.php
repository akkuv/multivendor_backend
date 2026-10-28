<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Events\NotificationCreated;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationRealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The `broadcast()` helper hands the event to the dispatcher, whose
        // PendingBroadcast dispatches it on destruction, so faking the event
        // class captures exactly what would go over the recipient's channel.
        Event::fake([NotificationCreated::class]);
    }

    public function test_admin_notification_is_broadcast_to_each_admin_on_their_own_channel(): void
    {
        $adminOne = User::factory()->superAdmin()->create();
        $adminTwo = User::factory()->superAdmin()->create();

        app(NotificationDispatcher::class)->notifyAdmins(
            NotificationType::NewVendorRegistered,
            'New vendor registered',
            'A new vendor is waiting for review.',
            ['vendor_id' => 7],
        );

        Event::assertDispatched(function (NotificationCreated $event) use ($adminOne) {
            return $event->userId === $adminOne->id
                && $event->type === NotificationType::NewVendorRegistered->value
                && $event->title === 'New vendor registered'
                && $event->message === 'A new vendor is waiting for review.'
                && $event->payload === ['vendor_id' => 7];
        });

        Event::assertDispatched(function (NotificationCreated $event) use ($adminTwo) {
            return $event->userId === $adminTwo->id;
        });
    }

    public function test_notification_to_a_single_user_is_broadcast_to_that_user(): void
    {
        $customer = User::factory()->customer()->create();

        app(NotificationDispatcher::class)->send(
            $customer,
            NotificationType::OrderStatus,
            'Order updated',
            'Your order #12 has shipped.',
            ['order_id' => 12],
            false,
        );

        Event::assertDispatched(function (NotificationCreated $event) use ($customer) {
            return $event->userId === $customer->id
                && $event->type === NotificationType::OrderStatus->value
                && $event->title === 'Order updated'
                && $event->message === 'Your order #12 has shipped.'
                && $event->payload === ['order_id' => 12];
        });
    }
}
