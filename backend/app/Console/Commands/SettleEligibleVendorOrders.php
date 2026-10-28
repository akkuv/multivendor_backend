<?php

namespace App\Console\Commands;

use App\Services\Order\VendorSettlementService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

#[Signature('commission:settle {--as-of= : Settle as if now was this date (ISO 8601). Mainly for testing.}')]
#[Description('Settle vendor commissions for delivered orders whose return window has elapsed.')]
class SettleEligibleVendorOrders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(VendorSettlementService $settlementService): int
    {
        $asOf = $this->option('as-of')
            ? Carbon::parse((string) $this->option('as-of'))
            : null;

        try {
            $settled = $settlementService->settleEligible($asOf);
        } catch (Throwable $e) {
            $this->error(__('Settlement failed: ').$e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$settled} order(s) settled.");

        return self::SUCCESS;
    }
}
