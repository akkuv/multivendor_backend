<?php

namespace App\Models;

use App\Enums\SupportSenderRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    /** @use HasFactory<SupportMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'support_conversation_id',
        'sender_user_id',
        'sender_role',
        'body',
        'image_url',
        'is_bot',
        'read_at',
    ];

    protected $casts = [
        'is_bot' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_bot' => false,
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'support_conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /**
     * Messages the given role has not opened yet. The assistant's replies count
     * as unread for the vendor, since they are genuinely waiting to be read.
     *
     * @param  Builder<SupportMessage>  $query
     * @return Builder<SupportMessage>
     */
    public function scopeUnreadFor(Builder $query, string $role): Builder
    {
        return $query
            ->whereNull('read_at')
            ->where('sender_role', '!=', $role);
    }

    /**
     * Bot replies have no author; the role is what the UI renders instead.
     */
    public function authorName(): string
    {
        if ($this->is_bot) {
            return SupportSenderRole::Bot->label();
        }

        return $this->sender?->name ?? SupportSenderRole::tryFrom($this->sender_role)?->label() ?? 'Unknown';
    }
}
