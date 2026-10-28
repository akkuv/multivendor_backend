<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sessions_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/sessions')
            ->assertStatus(401);
    }

    public function test_sessions_lists_current_active_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token');

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonCount(1, 'data.sessions')
            ->assertJsonPath('data.sessions.0.is_current', true);
    }

    public function test_revoke_session_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/auth/sessions/1')
            ->assertStatus(401);
    }

    public function test_revoke_session_deletes_the_token(): void
    {
        $user = User::factory()->create();
        $secondToken = $user->createToken('auth-token');

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/auth/sessions/'.$secondToken->accessToken->id)
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => (int) $secondToken->accessToken->id,
        ]);
    }

    public function test_revoke_session_returns_404_for_unknown_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/auth/sessions/99999')
            ->assertStatus(404);
    }
}
