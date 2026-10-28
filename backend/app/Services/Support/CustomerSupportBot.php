<?php

namespace App\Services\Support;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\ProductReturn;
use App\Models\User;

/**
 * Rule-based support assistant for the customer <-> admin conversation.
 *
 * Customers ask a completely different set of questions from vendors, so they
 * get their own intent table rather than a diluted shared one. Every answer is
 * read from the customer's own live rows — their orders, returns, refunds and
 * wallet — so a reply can never contradict what the app is showing them.
 *
 * Anything unrecognised, and anything that needs a human decision (a refund
 * exception, a lost parcel), is handed to an admin rather than guessed at.
 */
class CustomerSupportBot
{
    /**
     * Matched in order, so more specific intents come before broader ones.
     *
     * `handler` names a private method below rather than holding a callable,
     * because a const array cannot hold closures and a callable array would be
     * invoked statically.
     *
     * @var list<array{keywords: list<string>, handler: string}>
     */
    private const INTENTS = [
        [
            'keywords' => ['where is my order', 'track my order', 'order status', 'status of my order', 'has my order', 'order yet', 'my order arrived'],
            'handler' => 'answerOrderStatus',
        ],
        [
            'keywords' => ['cancel my order', 'cancel order', 'want to cancel', 'stop my order'],
            'handler' => 'answerCancelOrder',
        ],
        [
            'keywords' => ['refund', 'money back', 'reimburse', 'when will i get my money', 'refund status'],
            'handler' => 'answerRefund',
        ],
        [
            'keywords' => ['return', 'exchange', 'wrong item', 'damaged', 'defective', 'send it back', 'return status'],
            'handler' => 'answerReturn',
        ],
        [
            'keywords' => ['payment', 'paid but', 'payment failed', 'payment not', 'double charged', 'charged twice', 'did my payment go', 'cod', 'cash on delivery'],
            'handler' => 'answerPayment',
        ],
        [
            'keywords' => ['wallet', 'my balance', 'wallet balance', 'credit', 'top up', 'recharge'],
            'handler' => 'answerWallet',
        ],
        [
            'keywords' => ['coupon', 'promo code', 'discount code', 'voucher', 'offer code', 'apply offer'],
            'handler' => 'answerCoupon',
        ],
        [
            'keywords' => ['delivery', 'shipping', 'courier', 'shipping charge', 'how long', 'when will it arrive', 'shipping address', 'change address'],
            'handler' => 'answerDelivery',
        ],
        [
            'keywords' => ['change my email', 'change my phone', 'change my name', 'account details', 'my account', 'forgot password', 'reset password', 'change my address'],
            'handler' => 'answerAccount',
        ],
        [
            'keywords' => ['hello', 'hi ', 'hi!', 'hey', 'good morning', 'good afternoon', 'good evening'],
            'handler' => 'answerGreeting',
        ],
    ];

    /**
     * Prompts offered in the chat before the customer types anything.
     *
     * @return list<string>
     */
    public function suggestions(): array
    {
        return [
            'Where is my order?',
            'How do I return something?',
            'When will I get my refund?',
            'What is my wallet balance?',
            'Why was my payment charged twice?',
        ];
    }

    /**
     * The bot reply for a customer message, or null when a human must take over.
     */
    public function replyTo(string $message, User $customer): ?string
    {
        $normalised = mb_strtolower(trim($message));

        if ($normalised === '') {
            return null;
        }

        foreach (self::INTENTS as $intent) {
            if (! $this->matchesAny($normalised, $intent['keywords'])) {
                continue;
            }

            $answer = $this->dispatch($intent['handler'], $customer, $normalised);

            if ($answer !== null && $answer !== '') {
                return $answer;
            }
        }

        return null;
    }

    /**
     * Route a matched intent to its handler.
     *
     * The normalised message is threaded through because several answers are
     * about "that order" — a number in the message is what picks it.
     */
    private function dispatch(string $handler, User $customer, string $message): ?string
    {
        return match ($handler) {
            'answerOrderStatus' => $this->answerOrderStatus($customer, $message),
            'answerCancelOrder' => $this->answerCancelOrder($customer, $message),
            'answerRefund' => $this->answerRefund($customer),
            'answerReturn' => $this->answerReturn($customer),
            'answerPayment' => $this->answerPayment($customer, $message),
            'answerWallet' => $this->answerWallet($customer),
            'answerCoupon' => $this->answerCoupon($customer),
            'answerDelivery' => $this->answerDelivery($customer, $message),
            'answerAccount' => $this->answerAccount($customer),
            'answerGreeting' => $this->answerGreeting($customer),
            default => null,
        };
    }

    /**
     * The bot always contributes something: either a real answer, or a notice
     * that a human has been looped in.
     */
    public function replyOrHandoff(string $message, User $customer): string
    {
        return $this->replyTo($message, $customer) ?? $this->handoffNotice();
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

    private function money(float|int|string|null $amount): string
    {
        return config('commission.currency', 'INR').' '.number_format((float) $amount, 2);
    }

    /**
     * The order this question is about: an order number in the message if there
     * is one, otherwise the most recent order.
     */
    private function referencedOrder(User $customer, string $message): ?Order
    {
        $orders = Order::query()->where('user_id', $customer->id);

        if (preg_match('/\b(\d{6,})\b/', $message, $matches) === 1) {
            $referenced = (clone $orders)
                ->where(function ($query) use ($matches): void {
                    $query->where('order_number', $matches[1])
                        ->orWhere('id', $matches[1]);
                })
                ->first();

            if ($referenced !== null) {
                return $referenced;
            }
        }

        return $orders->latest('id')->first();
    }

    private function noOrders(): string
    {
        return 'I cannot see any orders on your account yet. Once you place one, its live status will show up here and in My Orders.';
    }

    private function answerGreeting(User $user): string
    {
        $name = trim((string) $user->name);

        $greeting = $name !== '' ? "Hello {$name}!" : 'Hello!';

        return $greeting.' I am the marketplace support assistant. I can check your order status, returns, refunds, payments and wallet. If you need a person, just ask — an admin will join this chat.';
    }

    private function answerOrderStatus(User $user, string $message = ''): ?string
    {
        $order = $this->referencedOrder($user, $message);

        if ($order === null) {
            return $this->noOrders();
        }

        $status = $order->order_status instanceof OrderStatus
            ? $order->order_status
            : OrderStatus::tryFrom((string) $order->order_status);

        $label = $status?->label() ?? (string) $order->order_status;

        $detail = match (true) {
            $order->shipment !== null && $order->shipment->tracking_number !== null => sprintf(
                'It shipped via %s and the tracking number is %s. Open Track Order in your account for the live scan history.',
                $order->shipment->courier_name ?: 'the courier',
                $order->shipment->tracking_number,
            ),
            $status === OrderStatus::Delivered => 'It has been delivered. If anything is wrong with it, raise a return within the return window and I can explain how.',
            $status === OrderStatus::Cancelled => 'It was cancelled, so nothing will be delivered. If you were charged, the refund is issued separately and I can check that for you.',
            default => 'It is still moving through fulfilment. Open My Orders for the item-by-item breakdown, and Track Order once it ships.',
        };

        return sprintf(
            'Order %s is currently %s, for %s. %s',
            $order->order_number,
            $label,
            $this->money($order->total),
            $detail,
        );
    }

    private function answerCancelOrder(User $user, string $message = ''): ?string
    {
        $order = $this->referencedOrder($user, $message);

        if ($order === null) {
            return $this->noOrders();
        }

        $status = $order->order_status instanceof OrderStatus
            ? $order->order_status
            : OrderStatus::tryFrom((string) $order->order_status);

        if ($status === null || ! $status->canTransitionTo(OrderStatus::Cancelled)) {
            return sprintf(
                'Order %s is %s, so it can no longer be cancelled from your account. If it has not shipped, message an admin here and they will look at it for you.',
                $order->order_number,
                $status?->label() ?? (string) $order->order_status,
            );
        }

        return sprintf(
            'Order %s is still %s, so you can cancel it yourself. Open My Orders, pick the order and choose Cancel. Any amount already charged is refunded to the original payment method.',
            $order->order_number,
            $status->label(),
        );
    }

    private function answerRefund(User $user): ?string
    {
        $refund = $user->orders()
            ->whereHas('refunds')
            ->with('refunds')
            ->latest('id')
            ->first()?->refunds()
            ->latest('id')
            ->first();

        if ($refund === null) {
            return 'I cannot see a refund on your account yet. Refunds are raised once a return is approved, or straight away if an order was cancelled or never delivered. If you are expecting one that is not showing, tell me the order number here and an admin will trace it.';
        }

        return sprintf(
            'The most recent refund is %s on order %s, and it is marked "%s". Refunds go back to the original payment method, or to your marketplace wallet when the original method no longer works. Bank timelines are usually 3 to 5 working days once we release it.',
            $this->money($refund->amount),
            (string) ($refund->order?->order_number ?? ('#'.$refund->order_id)),
            (string) $refund->status,
        );
    }

    private function answerReturn(User $user): ?string
    {
        $latest = ProductReturn::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $state = match ((string) $latest?->status) {
            ReturnStatus::Requested->value => 'has been requested and is waiting for the seller to review it',
            ReturnStatus::Approved->value => 'is approved and moving to pickup',
            ReturnStatus::PickupScheduled->value => 'has a pickup scheduled',
            ReturnStatus::PickedUp->value => 'has been picked up and is on its way back',
            ReturnStatus::Received->value => 'has been received and is being inspected',
            ReturnStatus::Inspecting->value => 'is being inspected right now',
            ReturnStatus::ApprovedForRefund->value => 'is approved and the refund is being released',
            ReturnStatus::Refunded->value => 'has been refunded',
            ReturnStatus::Rejected->value => 'was rejected',
            ReturnStatus::Closed->value => 'is closed',
            default => null,
        };

        if ($latest !== null && $state !== null) {
            return sprintf(
                'Your latest return request #%d %s. You can follow the whole timeline under My Returns, including the reason and any images you sent.',
                $latest->id,
                $state,
            );
        }

        return 'Raise a return from My Orders: open the order, pick the item and choose Return. Add a reason and photos if you can — it speeds up approval a lot. Returns are accepted while the item is within its return window, and you can withdraw a request yourself while the seller has not acted on it yet.';
    }

    private function answerPayment(User $user, string $message = ''): ?string
    {
        $order = $this->referencedOrder($user, $message);

        if ($order === null) {
            return $this->noOrders();
        }

        $status = $order->payment_status instanceof PaymentStatus
            ? $order->payment_status
            : PaymentStatus::tryFrom((string) $order->payment_status);

        $method = str_replace('_', ' ', (string) ($order->payment_method ?? 'your chosen method'));

        return match ($status) {
            PaymentStatus::Paid => sprintf(
                'Order %s is paid in full — %s by %s. If money left your account twice for this order, tell me here and an admin will trace both charges and reverse the duplicate.',
                $order->order_number,
                $this->money($order->total),
                $method,
            ),
            PaymentStatus::Failed => sprintf(
                'The payment for order %s did not go through, so the order was not placed. You can retry from My Orders, or use a different method.',
                $order->order_number,
            ),
            PaymentStatus::CodPending => sprintf(
                'Order %s is cash on delivery — nothing has been charged yet. Please keep the exact amount ready, and pay the courier when the parcel arrives.',
                $order->order_number,
            ),
            PaymentStatus::Refunded => sprintf(
                'Order %s has been refunded in full. It takes 3 to 5 working days to appear with your bank.',
                $order->order_number,
            ),
            PaymentStatus::PartiallyRefunded => sprintf(
                'Order %s has been partially refunded. The remaining balance is being collected on delivery or already settled.',
                $order->order_number,
            ),
            default => sprintf(
                'Order %s is awaiting a %s payment of %s. If you have already paid and it is not showing, give me the transaction reference in your next message and an admin will check it.',
                $order->order_number,
                $method,
                $this->money($order->total),
            ),
        };
    }

    private function answerWallet(User $user): ?string
    {
        $wallet = $user->wallet;

        if ($wallet === null) {
            return 'I cannot find a wallet on your account yet. A wallet is created when you place an order, and any refund you cannot take back to the original payment method lands there instead.';
        }

        $pending = (float) $wallet->pending_balance;

        $extra = $pending > 0
            ? sprintf(' A further %s is still settling and is not spendable yet.', $this->money($pending))
            : '';

        return sprintf(
            'Your wallet balance is %s, and %s is still settling.%s Open Wallet in your account for the full transaction history, and use Recharge to top it up.',
            $this->money($wallet->available_balance),
            $this->money($pending),
            $extra,
        );
    }

    private function answerCoupon(User $user): string
    {
        return 'Coupons are applied in the cart before you pay — look for the promo code box on the cart page. Each code has its own minimum spend, expiry and category limits, and an expired or ineligible code will say so at the point you try it. Only one code applies per order, and the bigger of the eligible discounts is used when a product already has a sale price.';
    }

    private function answerDelivery(User $user, string $message = ''): ?string
    {
        $order = $this->referencedOrder($user, $message);

        if ($order !== null && $order->shipment?->tracking_number !== null) {
            return sprintf(
                'Order %s ships with %s. The tracking number is %s, and Track Order in your account shows every scan as the courier updates it. Delivery addresses can be changed from the order page until the parcel is handed to the courier.',
                $order->order_number,
                $order->shipment->courier_name ?: 'our courier',
                $order->shipment->tracking_number,
            );
        }

        return 'Orders are dispatched once every seller confirms them, and you get a tracking number the moment the parcel is picked up — most parcels reach you within a week of dispatch. You can see the shipping charge and courier on the order page, and change the delivery address until dispatch. Track Order shows the live scan history.';
    }

    private function answerAccount(User $user): string
    {
        return 'You can change your name, email, phone and saved addresses from your account pages. For a password reset use the reset link on the login screen — it emails a new one. Anything security related, like locking out an old device, needs an admin: ask here and one will help.';
    }
}
