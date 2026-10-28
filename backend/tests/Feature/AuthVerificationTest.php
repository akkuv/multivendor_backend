<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VerificationCode;
use App\Notifications\SendVerificationCodeNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_email_verification_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/send-email-verification')
            ->assertStatus(401);
    }

    public function test_send_email_verification_sends_code_and_creates_verification_record(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/send-email-verification')
            ->assertOk()
            ->assertJson(['success' => true]);

        Notification::assertSentTo($user, SendVerificationCodeNotification::class);

        $this->assertDatabaseHas('verification_codes', [
            'user_id' => $user->id,
            'purpose' => VerificationCode::PURPOSE_EMAIL,
            'channel' => VerificationCode::CHANNEL_EMAIL,
            'used_at' => null,
        ]);
    }

    public function test_verify_email_marks_user_as_verified(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $code = VerificationCode::create([
            'user_id' => $user->id,
            'purpose' => VerificationCode::PURPOSE_EMAIL,
            'channel' => VerificationCode::CHANNEL_EMAIL,
            'target' => $user->email,
            'code' => '123456',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $this->postJson('/api/v1/auth/verify-email', ['code' => '123456'])
            ->assertOk()
            ->assertJsonPath('data.email_verified_at', fn ($value) => $value !== null);

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('verification_codes', [
            'id' => $code->id,
            'used_at' => Carbon::now(),
        ]);
    }

    public function test_verify_email_rejects_wrong_code(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        VerificationCode::create([
            'user_id' => $user->id,
            'purpose' => VerificationCode::PURPOSE_EMAIL,
            'channel' => VerificationCode::CHANNEL_EMAIL,
            'target' => $user->email,
            'code' => '123456',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $this->postJson('/api/v1/auth/verify-email', ['code' => '999999'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_send_otp_requires_phone_on_file(): void
    {
        $user = User::factory()->create(['phone' => null]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/send-otp')
            ->assertStatus(422);
    }

    public function test_send_otp_creates_phone_verification_code(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/send-otp')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('verification_codes', [
            'user_id' => $user->id,
            'purpose' => VerificationCode::PURPOSE_PHONE,
            'channel' => VerificationCode::CHANNEL_SMS,
        ]);
    }

    public function test_verify_otp_marks_phone_as_verified(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        VerificationCode::create([
            'user_id' => $user->id,
            'purpose' => VerificationCode::PURPOSE_PHONE,
            'channel' => VerificationCode::CHANNEL_SMS,
            'target' => $user->phone,
            'code' => '654321',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $this->postJson('/api/v1/auth/verify-otp', ['code' => '654321'])
            ->assertOk()
            ->assertJsonPath('data.phone_verified_at', fn ($value) => $value !== null);

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }
}
