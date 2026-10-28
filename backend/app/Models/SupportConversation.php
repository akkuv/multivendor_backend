<?php

namespace App\Models;

use App\Enums\SupportConversationKind;
use App\Enums\SupportRequesterType;
use App\Enums\SupportSenderRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportConversation extends Model
{
    /** @use HasFactory<SupportConversationFactory> */
    use HasFactory;

    protected $fillable = [
        'kind',
        'order_id',
        'product_id',
        'requester_type',
        'vendor_id',
        'vendor_user_id',
        'customer_user_id',
        'subject',
        'last_message_at',
        'last_admin_reply_at',
        'last_requester_reply_at',
        'is_closed',
        'closed_at',
    ];

    protected $casts = [
        'kind' => SupportConversationKind::class,
        'requester_type' => SupportRequesterType::class,
        'last_message_at' => 'datetime',
        'last_admin_reply_at' => 'datetime',
        'last_requester_reply_at' => 'datetime',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
    ];

    /**
     * Defaults for the singleton row created on a requester's first message, so
     * a freshly created conversation never reports a null cast as missing data.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => SupportConversationKind::General->value,
        'requester_type' => SupportRequesterType::Vendor->value,
        'is_closed' => false,
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function vendorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_user_id');
    }

    public function customerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * The asking customer's orders, most recent first. A product-query thread
     * loads this constrained to orders that include the conversation's product
     * (scoped with whereHas from the controller, where the product id is known),
     * then exposes them as the thread's purchase history. The constraint can not
     * live here: eager loading builds relations on a blank instance, so an
     * attribute-dependent where clause would silently drop every order.
     *
     * @return HasMany<Order, $this>
     */
    public function customerOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id', 'customer_user_id')
            ->orderByDesc('id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'support_conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportMessage::class, 'support_conversation_id')->latestOfMany();
    }

    /**
     * Limit a query to one side of the marketplace, so the vendor inbox and the
     * customer inbox never show each other's threads.
     *
     * @param  Builder<SupportConversation>  $query
     * @return Builder<SupportConversation>
     */
    public function scopeForRequesterType(Builder $query, SupportRequesterType $type): Builder
    {
        return $query->where('requester_type', $type->value);
    }

    /**
     * Limit a query to one kind of conversation, so everyday support threads and
     * order queries never leak into each other's queues.
     *
     * @param  Builder<SupportConversation>  $query
     * @return Builder<SupportConversation>
     */
    public function scopeForKind(Builder $query, SupportConversationKind $kind): Builder
    {
        return $query->where('kind', $kind->value);
    }

    /**
     * The role the requester of this conversation posts under.
     */
    public function requesterRole(): SupportSenderRole
    {
        return SupportSenderRole::requesterFor($this->requester_type);
    }

    /**
     * Whether a message from this role counts as requester-side activity.
     *
     * For most threads that is just the requester who opened them. Product
     * queries are tri-party, though: the vendor sells the product the customer
     * is asking about, so a vendor reply also sits with the admin team and
     * reopens a closed thread just like a customer writing again.
     */
    public function isRequesterSide(SupportSenderRole $role): bool
    {
        if ($role === $this->requesterRole()) {
            return true;
        }

        return $this->kind === SupportConversationKind::ProductQuery
            && $role === SupportSenderRole::Vendor;
    }

    /**
     * The user who opened the conversation, whichever side they are on.
     */
    public function requesterUser(): ?User
    {
        return $this->requester_type === SupportRequesterType::Vendor
            ? $this->vendorUser
            : $this->customerUser;
    }

    /**
     * True when the requester spoke last, so the thread is sitting with an admin.
     */
    public function isWaitingOnAdmin(): bool
    {
        return $this->last_requester_reply_at !== null
            && ($this->last_admin_reply_at === null
                || $this->last_requester_reply_at->greaterThan($this->last_admin_reply_at));
    }
}
