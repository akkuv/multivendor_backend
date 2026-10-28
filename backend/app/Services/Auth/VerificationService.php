<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class VerificationService
{
    public const DEFAULT_TTL_MINUTES = 10;

    public function generate(User $user, string $purpose, string $channel, ?string $target = null, int $ttlMinutes = self::DEFAULT_TTL_MINUTES): VerificationCode
    {
        $code = (string) random_int(100000, 999999);

        return VerificationCode::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'channel' => $channel,
            'target' => $target ?? $this->defaultTarget($user, $channel),
            'code' => $code,
            'expires_at' => Carbon::now()->addMinutes($ttlMinutes),
        ]);
    }

    public function verify(User $user, string $purpose, string $channel, string $code, ?string $target = null): bool
    {
        $verification = VerificationCode::query()
            ->forUser($user->id, $purpose, $channel, $target ?? $this->defaultTarget($user, $channel))
            ->where('code', $code)
            ->latest()
            ->first();

        if (! $verification) {
            $this->bumpLatestAttempt($user, $purpose, $channel, $target);

            throw ValidationException::withMessages([
                'code' => [__('Invalid verification code.')],
            ]);
        }

        if ($verification->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => [__('This verification code has expired. Please request a new one.')],
            ]);
        }

        if ($verification->attempts >= VerificationCode::MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'code' => [__('Too many wrong attempts. Please request a new code.')],
            ]);
        }

        if (! hash_equals($verification->code, $code)) {
            $verification->increment('attempts');

            throw ValidationException::withMessages([
                'code' => [__('Invalid verification code.')],
            ]);
        }

        $verification->forceFill(['used_at' => now()])->save();

        return true;
    }

    public function markEmailVerified(User $user): User
    {
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    public function markPhoneVerified(User $user): User
    {
        $user->forceFill(['phone_verified_at' => now()])->save();

        return $user;
    }

    protected function defaultTarget(User $user, string $channel): string
    {
        return $channel === VerificationCode::CHANNEL_SMS ? (string) $user->phone : (string) $user->email;
    }

    protected function bumpLatestAttempt(User $user, string $purpose, string $channel, ?string $target = null): void
    {
        $latest = VerificationCode::query()
            ->forUser($user->id, $purpose, $channel, $target ?? $this->defaultTarget($user, $channel))
            ->whereNull('used_at')
            ->latest()
            ->first();

        if ($latest) {
            $latest->increment('attempts');
        }
    }
}
