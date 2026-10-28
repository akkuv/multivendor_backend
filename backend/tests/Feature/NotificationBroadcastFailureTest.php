<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\PendingBroadcast;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class NotificationBroadcastFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function vendorUser(): User
    {
        $user = User::factory()->vendor()->create();
        VendorProfile::factory()->for($user)->create();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * A stopped/restarted websocket server must never fail the request that
     * triggered the notification. Before this guard, submitting a product threw
     * the broadcaster's BroadcastException and returned a 500.
     */
    public function test_product_submission_succeeds_when_broadcasting_is_unavailable(): void
    {
        $this->vendorUser();
        $category = Category::factory()->create();

        $factory = Mockery::mock(BroadcastFactory::class);
        // `broadcast()` builds a PendingBroadcast, which dispatches on destruction
        // and reaches `queue()` — where a real down broadcaster would throw.
        $factory->shouldReceive('event')->andReturnUsing(
            fn ($event) => new PendingBroadcast(app('events'), $event),
        );
        $factory->shouldReceive('queue')->andThrow(new BroadcastException('Reverb is offline.'));
        $this->app->instance(BroadcastFactory::class, $factory);

        $this->postJson('/api/v1/vendor/products', [
            'category_id' => $category->id,
            'name' => 'Resilient Product',
            'price' => 100,
            'stock' => 1,
        ])->assertCreated();

        $this->assertDatabaseHas('products', ['name' => 'Resilient Product']);
    }
}
