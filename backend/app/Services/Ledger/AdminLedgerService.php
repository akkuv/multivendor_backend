<?php

namespace App\Services\Ledger;

use App\Enums\AdminLedgerType;
use App\Models\AdminFinancialLedger;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Builder;

/**
 * Append-only log for every financial record the platform moves. No admin
 * money movement (refund, commission reversal, settlement, payout) may happen
 * without a matching ledger entry.
 */
class AdminLedgerService
{
    public function record(
        AdminLedgerType $type,
        float $amount,
        string $description,
        ?array $reference = null,
    ): AdminFinancialLedger {
        return AdminFinancialLedger::create(array_filter([
            'type' => $type->value,
            'amount' => $amount,
            'description' => $description,
            'order_id' => $reference['order_id'] ?? null,
            'refund_id' => $reference['refund_id'] ?? null,
            'commission_id' => $reference['commission_id'] ?? null,
            'vendor_id' => $reference['vendor_id'] ?? null,
            'user_id' => $reference['user_id'] ?? null,
        ], fn ($value) => $value !== null));
    }

    public function recordRefund(Refund $refund): AdminFinancialLedger
    {
        $order = $refund->order;
        $refundedFor = $refund->productReturn
            ? "Return {$refund->productReturn->id}"
            : ($refund->orderItem?->product_name ?? "Order {$order?->order_number}");

        return $this->record(
            AdminLedgerType::Refund,
            (float) $refund->amount,
            "Refund {$refund->amount} for {$refundedFor}".
                " (method: {$refund->method}) — order {$order?->order_number}.",
            ['order_id' => $refund->order_id, 'refund_id' => $refund->id, 'user_id' => $refund->user_id],
        );
    }

    public function recordCommissionReversal(
        float $amount,
        string $description,
        int $commissionId,
        int $vendorId,
        ?int $orderId = null,
    ): AdminFinancialLedger {
        return $this->record(
            AdminLedgerType::CommissionReversal,
            $amount,
            $description,
            ['commission_id' => $commissionId, 'vendor_id' => $vendorId, 'order_id' => $orderId],
        );
    }

    public function entries(): Builder
    {
        return AdminFinancialLedger::query();
    }
}
