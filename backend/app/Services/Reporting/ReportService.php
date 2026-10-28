<?php

namespace App\Services\Reporting;

use App\Enums\ReportType;
use App\Models\CommissionTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductReturn;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\User;
use App\Models\VendorPayout;
use App\Models\VendorProfile;
use App\Models\VendorWalletTransaction;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    // ------------------------------------------------------------------ //
    // Per-report data (admin and vendor share; vendor scopes by vendorId)
    // ------------------------------------------------------------------ //

    /** @param  array<string, mixed>  $filters */
    public function sales(array $filters = [], ?int $vendorId = null): array
    {
        $query = Order::query()
            ->select(['orders.*', 'users.name as customer_name', 'users.email as customer_email'])
            ->leftJoin('users', 'users.id', '=', 'orders.user_id')
            ->when($vendorId !== null, fn ($q) => $q->whereExists(function ($sub) use ($vendorId) {
                $sub->selectRaw('1')->from('order_items')
                    ->whereColumn('order_items.order_id', 'orders.id')
                    ->where('order_items.vendor_id', $vendorId);
            }));

        $this->applyDateRange($query, 'orders.created_at', $filters);
        if (! empty($filters['vendor_id'])) {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->selectRaw('1')->from('order_items')
                    ->whereColumn('order_items.order_id', 'orders.id')
                    ->where('order_items.vendor_id', $filters['vendor_id']);
            });
        }

        return $this->finalize($this->summary($query, ['total_orders' => 'count', 'total_revenue' => 'sum:orders.total']), $query);
    }

    /** @param  array<string, mixed>  $filters */
    public function orders(array $filters = [], ?int $vendorId = null): array
    {
        $query = OrderItem::query()
            ->select([
                'order_items.id',
                'order_items.order_id',
                'order_items.vendor_id',
                'order_items.product_id',
                'order_items.product_name',
                'order_items.quantity',
                'order_items.unit_price',
                'order_items.total',
                'orders.order_number',
                'orders.user_id',
                'orders.order_status',
                'orders.payment_status',
                'orders.created_at',
            ])
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->when($vendorId !== null, fn ($q) => $q->where('order_items.vendor_id', $vendorId));

        $this->applyDateRange($query, 'orders.created_at', $filters);
        $this->applyVendorAndCategory($query, $filters, forceScope: $vendorId === null);

        return $this->finalize($this->summary($query, ['total_items' => 'sum:order_items.quantity', 'gross' => 'sum:order_items.total']), $query);
    }

    /** @param  array<string, mixed>  $filters */
    public function products(array $filters = [], ?int $vendorId = null): array
    {
        $query = OrderItem::query()
            ->select(['order_items.*', 'orders.created_at'])
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->when($vendorId !== null, fn ($q) => $q->where('order_items.vendor_id', $vendorId))
            ->when(! empty($filters['category_id']), fn ($q) => $q->whereHas('product', fn ($p) => $p->where('category_id', $filters['category_id'])));

        $this->applyDateRange($query, 'orders.created_at', $filters);

        return $this->finalize($this->summary($query, ['total_items' => 'sum:order_items.quantity', 'gross' => 'sum:order_items.total']), $query);
    }

    /** @param  array<string, mixed>  $filters */
    public function vendors(array $filters = []): array
    {
        $query = VendorProfile::query()
            ->select(['vendor_profiles.*', 'users.name as owner_name', 'users.email as owner_email'])
            ->leftJoin('users', 'users.id', '=', 'vendor_profiles.user_id');

        $this->applyDateRange($query, 'vendor_profiles.created_at', $filters);

        return $this->finalize($this->summary($query, ['total_vendors' => 'count']), $query);
    }

    /** @param  array<string, mixed>  $filters */
    public function customers(array $filters = []): array
    {
        $query = User::query()->where('role', 'CUSTOMER');
        $this->applyDateRange($query, 'created_at', $filters);

        return $this->finalize($this->summary($query, ['total_customers' => 'count']), $query);
    }

    /** @param  array<string, mixed>  $filters */
    public function commissions(array $filters = [], ?int $vendorId = null): array
    {
        $query = CommissionTransaction::query()
            ->when($vendorId !== null, fn ($q) => $q->where('vendor_id', $vendorId));

        $this->applyDateRange($query, 'created_at', $filters);

        return $this->finalize($this->summary($query, ['total_commission' => 'sum:amount']), $query);
    }

    /** @param  array<string, mixed>  $filters */
    public function payments(array $filters = []): array
    {
        $query = Payment::query();
        $this->applyDateRange($query, 'created_at', $filters);

        return $this->finalize($this->summary($query, ['total_payments' => 'sum:amount']), $query);
    }

    /** @param  array<string, mixed>  $filters */
    public function refunds(array $filters = [], ?int $vendorId = null): array
    {
        $query = Refund::query()
            ->select(['refunds.*', 'orders.order_number'])
            ->leftJoin('orders', 'orders.id', '=', 'refunds.order_id')
            ->when($vendorId !== null, fn ($q) => $q->whereExists(function ($sub) use ($vendorId) {
                $sub->selectRaw('1')->from('order_items')
                    ->whereColumn('order_items.order_id', 'refunds.order_id')
                    ->where('order_items.vendor_id', $vendorId);
            }));

        $this->applyDateRange($query, 'refunds.created_at', $filters);

        return $this->finalize($this->summary($query, ['total_refunded' => 'sum:amount']), $query);
    }

    /** @param  array<string, mixed>  $filters */
    public function returns(array $filters = [], ?int $vendorId = null): array
    {
        $query = ProductReturn::query()
            ->select(['product_returns.*', 'orders.order_number'])
            ->leftJoin('orders', 'orders.id', '=', 'product_returns.order_id')
            ->when($vendorId !== null, fn ($q) => $q->whereExists(function ($sub) use ($vendorId) {
                $sub->selectRaw('1')->from('order_items')
                    ->whereColumn('order_items.order_id', 'product_returns.order_id')
                    ->where('order_items.vendor_id', $vendorId);
            }));

        $this->applyDateRange($query, 'product_returns.created_at', $filters);

        return $this->finalize($this->summary($query, ['total_returns' => 'count']), $query);
    }

    /** @param  array<string, mixed>  $filters */
    public function shipping(array $filters = []): array
    {
        $query = Shipment::query();
        $this->applyDateRange($query, 'created_at', $filters);

        return $this->finalize($this->summary($query, ['total_charges' => 'sum:charges']), $query);
    }

    /** @param  array<string, mixed>  $filters */
    public function wallet(array $filters = [], ?int $vendorId = null): array
    {
        $query = $vendorId !== null
            ? VendorWalletTransaction::query()->whereHas('wallet', fn ($q) => $q->where('vendor_id', $vendorId))
            : WalletTransaction::query();

        $this->applyDateRange($query, 'created_at', $filters);

        return $this->finalize($this->summary($query, ['total_volume' => 'sum:amount']), $query);
    }

    /** @param  array<string, mixed>  $filters */
    public function payouts(array $filters = [], ?int $vendorId = null): array
    {
        $query = VendorPayout::query()
            ->when($vendorId !== null, fn ($q) => $q->where('vendor_id', $vendorId));

        $this->applyDateRange($query, 'created_at', $filters);

        return $this->finalize($this->summary($query, ['total_payouts' => 'sum:amount']), $query);
    }

    // ------------------------------------------------------------------ //
    // Aggregation helpers
    // ------------------------------------------------------------------ //

    /**
     * @param  array<string, string>  $defs  map of summary key => ('count' | 'sum:column')
     * @return array<string, mixed>
     */
    private function summary(Builder $query, array $defs): array
    {
        $summary = [];
        $base = $query->clone();

        foreach ($defs as $key => $def) {
            if ($def === 'count') {
                $summary[$key] = $base->clone()->count();
            } elseif (str_starts_with($def, 'sum:')) {
                $column = substr($def, 4);
                $summary[$key] = round((float) $base->clone()->sum($column), 2);
            }
        }

        if (request()->boolean('by_day')) {
            $summary['by_day'] = $this->groupedByDay($query);
        }

        return $summary;
    }

    /** @return array<string, float|int> */
    private function groupedByDay(Builder $query): array
    {
        $column = $query->getModel()->qualifyColumn('created_at');

        $rows = $query->clone()
            ->select(
                DB::raw("DATE({$column}) as day"),
                DB::raw('COUNT(*) as count'),
            )
            ->groupByRaw("DATE({$column})")
            ->orderByRaw("DATE({$column})")
            ->get();

        return $rows->mapWithKeys(fn ($r) => [(string) $r->day => (int) $r->count])->all();
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  Builder<*>  $query
     * @return array<string, mixed>
     */
    private function finalize(array $summary, Builder $query): array
    {
        $limit = max(1, (int) request()->input('limit', 500));
        $items = $this->paginate($query, $limit);

        return [
            'summary' => $summary,
            'items' => $items,
        ];
    }

    /**
     * @param  Builder<*>  $query
     * @return LengthAwarePaginator<int, mixed>
     */
    private function paginate(Builder $query, int $perPage)
    {
        return $query->orderByDesc($query->getModel()->qualifyColumn('created_at'))->paginate($perPage);
    }

    /** @param  array<string, mixed>  $filters */
    private function applyDateRange(Builder $query, string $column, array $filters): void
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        if ($from && $to) {
            $query->whereBetween($column, [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()]);
        } elseif ($from) {
            $query->where($column, '>=', Carbon::parse($from)->startOfDay());
        } elseif ($to) {
            $query->where($column, '<=', Carbon::parse($to)->endOfDay());
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyVendorAndCategory(Builder $query, array $filters, bool $forceScope = false): void
    {
        if ($forceScope && ! empty($filters['vendor_id'])) {
            $query->where('order_items.vendor_id', $filters['vendor_id']);
        }
    }

    // ------------------------------------------------------------------ //
    // CSV export
    // ------------------------------------------------------------------ //

    /**
     * @param  array<string, mixed>  $filters
     * @return array{headers: list<string>, rows: Collection<int, array<int, mixed>>}
     */
    public function csv(string $type, array $filters = [], ?int $vendorId = null, bool $forVendor = false): array
    {
        $result = $this->dispatch(ReportType::from($type), $filters, $vendorId, $forVendor);
        $rows = collect($result['items']->items());

        $headers = $rows->isEmpty()
            ? []
            : array_keys($rows->first()->getAttributes());

        $body = $rows->map(fn ($item) => array_values(array_map(
            fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value,
            $item->getAttributes(),
        )));

        return ['headers' => $headers, 'rows' => $body];
    }

    /** @param  array<string, mixed>  $filters */
    private function dispatch(ReportType $type, array $filters, ?int $vendorId, bool $forVendor): array
    {
        if ($forVendor) {
            return match ($type) {
                ReportType::Sales => $this->sales($filters, $vendorId),
                ReportType::Orders => $this->orders($filters, $vendorId),
                ReportType::Products => $this->products($filters, $vendorId),
                ReportType::Commission => $this->commissions($filters, $vendorId),
                ReportType::Wallet => $this->wallet($filters, $vendorId),
                ReportType::Payouts => $this->payouts($filters, $vendorId),
                ReportType::Returns => $this->returns($filters, $vendorId),
                default => throw new \InvalidArgumentException('Unsupported vendor report type.'),
            };
        }

        return match ($type) {
            ReportType::Sales => $this->sales($filters),
            ReportType::Orders => $this->orders($filters),
            ReportType::Products => $this->products($filters),
            ReportType::Vendors => $this->vendors($filters),
            ReportType::Customers => $this->customers($filters),
            ReportType::Commission => $this->commissions($filters),
            ReportType::Payments => $this->payments($filters),
            ReportType::Refunds => $this->refunds($filters),
            ReportType::Returns => $this->returns($filters),
            ReportType::Shipping => $this->shipping($filters),
            ReportType::Wallet => $this->wallet($filters),
            ReportType::Payouts => $this->payouts($filters),
        };
    }
}
