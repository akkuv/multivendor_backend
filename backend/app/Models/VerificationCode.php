<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationCode extends Model
{
    public const PURPOSE_EMAIL = 'email';

    public const PURPOSE_PHONE = 'phone';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_SMS = 'sms';

    public const MAX_ATTEMPTS = 5;

    protected $fillable = [
        'user_id',
        'purpose',
        'channel',
        'target',
        'code',
        'attempts',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /** @var array<string, string> */
    protected $hidden = [
        'code',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('used_at')
            ->where('expires_at', '>', now());
    }

    public function scopeForUser(Builder $query, int $userId, string $purpose, string $channel, ?string $target = null): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->where('purpose', $purpose)
            ->where('channel', $channel)
            ->when($target !== null, fn (Builder $q) => $q->where('target', $target));
    }
}
