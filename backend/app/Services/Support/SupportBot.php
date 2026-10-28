<?php

namespace App\Services\Support;

use App\Enums\VendorPayoutStatus;
use App\Models\User;
use App\Models\VendorCommission;
use App\Models\VendorPayout;
use App\Models\VendorProfile;

/**
 * Rule-based support assistant for the vendor <-> admin conversation.
 *
 * Answers the questions vendors actually ask about money (payouts, commission,
 * settlement), account standing, shipping and returns — reading live figures
 * from the database so a reply can never drift from the real numbers.
 *
 * Anything unrecognised, and anything requiring a human decision, is handed to
 * an admin rather than guessed at.
 */
class SupportBot
{
    /**
     * Matched in order, so more specific intents are listed before broader ones.
     *
     * `handler` names a private method below rather than holding a callable,
     * because a const array cannot hold closures and a callable array would be
     * invoked statically.
     *
     * @var list<array{keywords: list<string>, handler: string}>
     */
    private const INTENTS = [
        [
            'keywords' => ['payout rejected', 'payout was rejected', 'payout got rejected', 'why was my payout', 'payout declined', 'payout denied'],
            'handler' => 'answerPayoutRejected',
        ],
        [
            'keywords' => ['payout pending', 'payout not received', 'payout not arrived', 'where is my payout', 'payout still', 'awaiting payout', 'when will i get paid'],
            'handler' => 'answerPayoutPending',
        ],
        [
            'keywords' => ['minimum payout', 'min payout', 'how much can i withdraw', 'withdraw limit', 'payout limit'],
            'handler' => 'answerPayoutLimits',
        ],
        [
            'keywords' => ['payout method', 'withdraw to', 'bank account payout', 'upi payout', 'how do i get paid', 'payout details'],
            'handler' => 'answerPayoutMethods',
        ],
        [
            'keywords' => ['settlement', 'return window', 'money not released', 'funds not released', 'commission not credited'],
            'handler' => 'answerSettlementDelay',
        ],
        [
            'keywords' => ['commission', 'what percentage', 'platform fee', 'take rate', 'deduction'],
            'handler' => 'answerCommission',
        ],
        [
            'keywords' => ['wallet balance', 'my balance', 'available balance', 'how much money', 'wallet amount'],
            'handler' => 'answerWalletBalance',
        ],
        [
            'keywords' => ['account status', 'pending approval', 'account approved', 'suspended', 'store rejected', 'verification', 'why am i suspended'],
            'handler' => 'answerAccountStatus',
        ],
        [
            'keywords' => ['shipping', 'shipment', 'delivery', 'tracking', 'courier', 'awb', 'label', 'pickup', 'manifest'],
            'handler' => 'answerShipping',
        ],
        [
            'keywords' => ['return', 'refund', 'reverse'],
            'handler' => 'answerReturns',
        ],
        [
            'keywords' => ['product rejected', 'product not approved', 'listing rejected', 'product approval', 'why is my product'],
            'handler' => 'answerProductListing',
        ],
        [
            'keywords' => ['missing order', 'wrong order', 'order not received', 'cancel my order', 'order issue', 'duplicate order'],
            'handler' => 'answerOrderIssue',
        ],
        [
            'keywords' => ['hello', 'hi ', 'hi!', 'hey', 'good morning', 'good afternoon', 'good evening'],
            'handler' => 'answerGreeting',
        ],
    ];

    /**
     * Prompts offered in the chat before the vendor types anything.
     *
     * @return list<string>
     */
    public function suggestions(): array
    {
        return [
            'When will my payout arrive?',
            'What is my commission rate?',
            'Why was my payout rejected?',
            'How do I ship an order?',
            'What is my account status?',
        ];
    }

    /**
     * The bot reply for a vendor message, or null when a human must take over.
     */
    public function replyTo(string $message, User $vendorUser): ?string
    {
        $normalised = mb_strtolower(trim($message));

        if ($normalised === '') {
            return null;
        }

        foreach (self::INTENTS as $intent) {
            if (! $this->matchesAny($normalised, $intent['keywords'])) {
                continue;
            }

            $answer = $this->dispatch($intent['handler'], $vendorUser);

            if ($answer !== null && $answer !== '') {
                return $answer;
            }
        }

        return null;
    }

    /**
     * Route a matched intent to its handler.
     */
    private function dispatch(string $handler, User $vendorUser): ?string
    {
        return match ($handler) {
            'answerPayoutRejected' => $this->answerPayoutRejected($vendorUser),
            'answerPayoutPending' => $this->answerPayoutPending($vendorUser),
            'answerPayoutLimits' => $this->answerPayoutLimits($vendorUser),
            'answerPayoutMethods' => $this->answerPayoutMethods($vendorUser),
            'answerSettlementDelay' => $this->answerSettlementDelay($vendorUser),
            'answerCommission' => $this->answerCommission($vendorUser),
            'answerWalletBalance' => $this->answerWalletBalance($vendorUser),
            'answerAccountStatus' => $this->answerAccountStatus($vendorUser),
            'answerShipping' => $this->answerShipping($vendorUser),
            'answerReturns' => $this->answerReturns($vendorUser),
            'answerProductListing' => $this->answerProductListing($vendorUser),
            'answerOrderIssue' => $this->answerOrderIssue($vendorUser),
            'answerGreeting' => $this->answerGreeting($vendorUser),
            default => null,
        };
    }

    /**
     * The bot always contributes something: either a real answer, or a notice
     * that a human has been looped in.
     */
    public function replyOrHandoff(string $message, User $vendorUser): string
    {
        return $this->replyTo($message, $vendorUser) ?? $this->handoffNotice();
    }

    public function handoffNotice(): string
    {
        return 'I do not have an answer for that one, so I have flagged it for the support team. An admin will reply in this chat shortly — your question is already saved.';
    }

    /**
     * @param  list<string>  $keywords
     */
    private function matchesAny(string $normalised, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($normalised, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function vendor(User $user): ?VendorProfile
    {
        return $user->vendorProfile;
    }

    private function money(float|int|string|null $amount): string
    {
        return config('commission.currency', 'INR').' '.number_format((float) $amount, 2);
    }

    private function noProfile(): string
    {
        return 'I could not find a vendor profile linked to this account, so I cannot read your figures. I have flagged this for the support team and an admin will reply here.';
    }

    private function answerGreeting(User $user): string
    {
        $store = $this->vendor($user)?->store_name;

        $greeting = $store !== null
            ? "Hello {$store}!"
            : 'Hello!';

        return $greeting.' I am the marketplace support assistant. I can answer questions about payouts, commission, settlement, shipping and account status. If you need a person, just ask — an admin will join this chat.';
    }

    private function answerPayoutPending(User $user): ?string
    {
        $vendor = $this->vendor($user);

        if (! $vendor) {
            return $this->noProfile();
        }

        $latest = VendorPayout::query()
            ->where('vendor_id', $vendor->id)
            ->whereIn('status', [VendorPayoutStatus::Pending, VendorPayoutStatus::Approved])
            ->latest('id')
            ->first();

        if (! $latest) {
            return 'You have no payout request in flight right now. Open Payouts from your dashboard to raise one — requests are reviewed by an admin before they are released.';
        }

        $state = $latest->status === VendorPayoutStatus::Approved
            ? 'has been approved and is queued for release'
            : 'is waiting for an admin to review it';

        return sprintf(
            'Your latest payout request #%d for %s %s. You will see the status change under Payouts in your dashboard as soon as an admin acts on it.',
            $latest->id,
            $this->money($latest->amount),
            $state,
        );
    }

    private function answerPayoutRejected(User $user): ?string
    {
        $vendor = $this->vendor($user);

        if (! $vendor) {
            return $this->noProfile();
        }

        $rejected = VendorPayout::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', VendorPayoutStatus::Rejected)
            ->latest('id')
            ->first();

        if (! $rejected) {
            return 'I cannot see a rejected payout on your account. If you were told a request was rejected, an admin needs to check it for you — I have flagged this.';
        }

        $note = trim((string) ($rejected->admin_note ?? ''));

        return $note !== ''
            ? sprintf(
                'Payout request #%d for %s was rejected. The admin note says: "%s" Fix that and submit a new request from your dashboard.',
                $rejected->id,
                $this->money($rejected->amount),
                $note,
            )
            : sprintf(
                'Payout request #%d for %s was rejected without a recorded reason. An admin will follow up here with the details.',
                $rejected->id,
                $this->money($rejected->amount),
            );
    }

    private function answerPayoutLimits(User $user): string
    {
        $min = (float) config('commission.min_payout_amount', 100);
        $max = config('commission.max_payout_amount');
        $maxText = $max ? ' and at most '.$this->money($max) : '';

        return sprintf(
            'You can request a payout of at least %s%s in a single request. Your available balance is always shown at the top of the Payouts page.',
            $this->money($min),
            $maxText,
        );
    }

    private function answerPayoutMethods(User $user): ?string
    {
        $vendor = $this->vendor($user);

        if (! $vendor) {
            return $this->noProfile();
        }

        $labels = array_map(
            fn (string $method): string => match ($method) {
                'bank_transfer' => 'bank transfer',
                'upi' => 'UPI',
                default => str_replace('_', ' ', $method),
            },
            (array) config('commission.payout_methods', []),
        );

        $list = $labels === [] ? 'saved payout details' : implode(' or ', $labels);

        $hasBank = trim((string) ($vendor->bank_account_number ?? '')) !== '';
        $hasUpi = trim((string) ($vendor->upi_id ?? '')) !== '';

        $detail = match (true) {
            $hasBank && $hasUpi => 'I can see both a bank account and a UPI id saved on your profile.',
            $hasBank => 'I can see a bank account saved on your profile, but no UPI id.',
            $hasUpi => 'I can see a UPI id saved on your profile, but no bank account.',
            default => 'I cannot see any payout details saved on your profile yet.',
        };

        return sprintf(
            'Payouts are released to your %s. %s Add or update them from Profile → Payout settings before requesting a payout, otherwise the request will be rejected.',
            $list,
            $detail,
        );
    }

    private function answerSettlementDelay(User $user): ?string
    {
        $vendor = $this->vendor($user);

        if (! $vendor) {
            return $this->noProfile();
        }

        $days = (int) config('commission.settlement_return_window_days', 7);

        $next = VendorCommission::query()
            ->where('vendor_id', $vendor->id)
            ->whereNotNull('return_window_ends_at')
            ->where('return_window_ends_at', '>', now())
            ->whereNull('settled_at')
            ->orderBy('return_window_ends_at')
            ->first();

        if ($next) {
            return sprintf(
                'Earnings are released %d days after delivery, so a delivered order stays in its return window until then. The next release on your account is %s — after that it moves to your wallet.',
                $days,
                $next->return_window_ends_at->format('d M Y'),
            );
        }

        return sprintf(
            'Earnings are released %d days after delivery, once the return window closes. Nothing is currently held in the return window on your account, so any settled commission should already be in your wallet.',
            $days,
        );
    }

    private function answerCommission(User $user): ?string
    {
        $vendor = $this->vendor($user);

        if (! $vendor) {
            return $this->noProfile();
        }

        $rate = (float) ($vendor->commission_rate ?? config('commission.default_rate', 10));

        return sprintf(
            'Your commission rate is %.2f%% of each order subtotal, deducted before your share is calculated. So on a %s subtotal you keep %s. Shipping charges are yours to keep.',
            $rate,
            $this->money(1000),
            $this->money(1000 - (1000 * ($rate / 100))),
        );
    }

    private function answerWalletBalance(User $user): ?string
    {
        $vendor = $this->vendor($user);

        if (! $vendor) {
            return $this->noProfile();
        }

        $wallet = $vendor->wallet;

        if (! $wallet) {
            return 'I cannot find a wallet record on your vendor profile yet. I have flagged this for an admin to set up.';
        }

        $pending = (float) $wallet->pending_balance;
        $held = (float) $wallet->held_balance;

        $extra = match (true) {
            $pending > 0 && $held > 0 => sprintf(
                '%s is still settling and %s is held.',
                $this->money($pending),
                $this->money($held),
            ),
            $pending > 0 => sprintf('%s is still settling.', $this->money($pending)),
            $held > 0 => sprintf('%s is held.', $this->money($held)),
            default => 'Nothing is pending or held at the moment.',
        };

        return sprintf(
            'Your available wallet balance is %s, and you have %s paid out in total. %s Open Wallet for the full transaction history, or Payouts to request a withdrawal.',
            $this->money($wallet->available_balance),
            $this->money($wallet->paid_total),
            $extra,
        );
    }

    private function answerAccountStatus(User $user): ?string
    {
        $vendor = $this->vendor($user);

        if (! $vendor) {
            return $this->noProfile();
        }

        return match ((string) $vendor->status) {
            'APPROVED' => sprintf(
                'Your store "%s" is approved and active, so you can list products and receive orders. Keep your documents current to avoid a hold.',
                $vendor->store_name,
            ),
            'PENDING' => sprintf(
                'Your store "%s" is still pending approval. Admins review new stores manually — this chat is the fastest way to chase it. You can keep editing draft products while you wait.',
                $vendor->store_name,
            ),
            'SUSPENDED' => sprintf(
                'Your store "%s" is suspended, so new orders and payouts are on hold. I cannot see why — ask here and an admin will pick it up and tell you.',
                $vendor->store_name,
            ),
            'REJECTED' => sprintf(
                'Your store "%s" was rejected. Update your details on your profile page and resubmit for approval.',
                $vendor->store_name,
            ),
            'INACTIVE' => sprintf(
                'Your store "%s" is inactive. Nothing is wrong per se — it is just not currently trading. Contact an admin here if you want it reactivated.',
                $vendor->store_name,
            ),
            default => sprintf(
                'Your store "%s" is currently marked "%s". I cannot explain that status, so I have flagged it for an admin.',
                $vendor->store_name,
                (string) $vendor->status,
            ),
        };
    }

    private function answerShipping(User $user): string
    {
        return 'Shipping runs through Shiprocket. Open Shipments to see every order that still needs a label, then pick a courier — we compare rates and show the cheapest option. Pickup requests and bulk manifests live on the same page, and tracking updates appear in Shipment detail as the courier scans.';
    }

    private function answerReturns(User $user): string
    {
        return 'When a customer raises a return it appears in your Returns list with a reason and any images. Accept it to start the refund, or decline with a note — declining requires a reason. Refunds go back to the customer, and an open return also pauses your commission until the return window closes.';
    }

    private function answerProductListing(User $user): string
    {
        return 'New and edited products are reviewed before they go live. Open Products to find a rejected item — the rejection reason is shown on the listing itself. Fix the flagged detail and re-submit, and it will be reviewed again. If the reason does not make sense, say so here and an admin will review it.';
    }

    private function answerOrderIssue(User $user): string
    {
        return 'For a missing, wrong or duplicate order, put the order number in your next message so an admin can pull it up and act on it. I cannot change orders myself, but passing the number here gets it to the right person quickly.';
    }
}
