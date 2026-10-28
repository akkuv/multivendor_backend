<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\VendorCommissionStatus;
use App\Enums\VendorStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\Refund;
use App\Models\User;
use App\Models\VendorCommission;
use App\Models\VendorOrder;
use App\Models\VendorPayout;
use App\Models\VendorProfile;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $days = (int) $request->integer('days', 30);

        ['start' => $start, 'end' => $end] = $this->period($days);

        $stats = $this->stats($start, $end);
        $today = $this->todayStats();
        $charts = $this->series($start, $end, $days);

        return $this->success(__('Admin dashboard data.'), [
            ...$stats,
            ...$today,
            ...$charts,
            'recent_orders' => $this->recentOrders($start, $end),
        ]);
    }

    protected function stats(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $customerCount = User::where('role', Role::Customer->value)
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $vendorCount = VendorProfile::whereBetween('created_at', [$start, $end])->count();
        $activeVendors = VendorProfile::where('status', VendorStatus::Approved->value)->count();
        $pendingVendors = VendorProfile::where('status', VendorStatus::Pending->value)->count();
        $productCount = Product::whereBetween('created_at', [$start, $end])->count();
        $pendingProducts = Product::where('approval_status', 'PENDING_APPROVAL')->count();
        $orderCount = Order::whereBetween('created_at', [$start, $end])->count();
        $revenue = Order::where('payment_status', PaymentStatus::Paid->value)
            ->whereBetween('created_at', [$start, $end])
            ->sum('total');
        // Sourced from the settlement ledger, not `order_items`: the "Commission
        // Earned" card links into the admin commission ledger, so it must count
        // the same rows that list shows.
        $commission = VendorCommission::whereBetween('created_at', [$start, $end])->sum('commission_amount');

        $vendorPayable = VendorCommission::where('status', VendorCommissionStatus::Settled->value)->sum('net_payable');

        $refunds = Refund::whereBetween('created_at', [$start, $end])->sum('amount');
        $returns = ProductReturn::whereBetween('created_at', [$start, $end])->count();

        $admin = User::where('role', Role::SuperAdmin->value)->orderBy('id')->first();
        $adminWalletBalance = $admin?->wallet
            ? round((float) ($admin->wallet->available_balance + $admin->wallet->pending_balance), 2)
            : 0.0;
        $payoutRequests = VendorPayout::where('status', 'PENDING')->count();

        return [
            'customer_count' => $customerCount,
            'vendor_count' => $vendorCount,
            'active_vendors' => $activeVendors,
            'pending_vendors' => $pendingVendors,
            'product_count' => $productCount,
            'pending_products' => $pendingProducts,
            'order_count' => $orderCount,
            'revenue' => round((float) $revenue, 2),
            'total_commission' => round((float) $commission, 2),
            'vendor_payable' => round((float) $vendorPayable, 2),
            'refunds' => round((float) $refunds, 2),
            'returns' => $returns,
            'wallet_balance' => $adminWalletBalance,
            'payout_requests' => $payoutRequests,
        ];
    }

    protected function todayStats(): array
    {
        $today = CarbonImmutable::today();

        return [
            'today_orders' => Order::whereDate('created_at', $today)->count(),
            'today_sales' => round((float) Order::where('payment_status', PaymentStatus::Paid->value)
                ->whereDate('created_at', $today)
                ->sum('subtotal'), 2),
        ];
    }

    protected function series(CarbonImmutable $start, CarbonImmutable $end, int $days): array
    {
        $sales = Order::query()
            ->selectRaw('DATE(created_at) as date, SUM(subtotal) as total, COUNT(*) as count')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $commission = VendorCommission::query()
            ->selectRaw('DATE(created_at) as date, SUM(commission_amount) as total')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $labels = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $labels[] = $day->format('Y-m-d');
        }

        $salesSeries = collect($labels)->map(fn (string $date) => [
            'date' => $date,
            'orders' => (int) ($sales[$date]->count ?? 0),
            'sales' => round((float) ($sales[$date]->total ?? 0), 2),
        ])->values();

        $commissionSeries = collect($labels)->map(fn (string $date) => [
            'date' => $date,
            'commission' => round((float) ($commission[$date]->total ?? 0), 2),
        ])->values();

        return [
            'sales_series' => $salesSeries,
            'commission_series' => $commissionSeries,
            'top_vendors' => $this->topVendors($start, $end),
            'top_categories' => $this->topCategories($start, $end),
            'top_products' => $this->topProducts($start, $end),
        ];
    }

    protected function topVendors(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return VendorOrder::query()
            ->select('vendor_id')
            ->selectRaw('SUM(total) as sales')
            ->with('vendor:id,store_name,logo')
            ->whereNotNull('vendor_id')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('vendor_id')
            ->orderByDesc('sales')
            ->limit(5)
            ->get()
            ->map(fn (VendorOrder $vo) => [
                'vendor_id' => $vo->vendor_id,
                'name' => $vo->vendor?->store_name ?? 'N/A',
                'logo' => $vo->vendor?->logo,
                'sales' => round((float) $vo->sales, 2),
            ])
            ->values()
            ->all();
    }

    protected function topCategories(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return DB::table('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->select('categories.id', 'categories.name')
            ->selectRaw('SUM(order_items.total) as sales')
            ->whereBetween('order_items.created_at', [$start, $end])
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('sales')
            ->limit(5)
            ->get()
            ->map(fn (object $row) => [
                'category_id' => $row->id,
                'name' => $row->name,
                'sales' => round((float) $row->sales, 2),
            ])
            ->values()
            ->all();
    }

    protected function topProducts(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return OrderItem::query()
            ->select('product_id')
            ->selectRaw('SUM(total) as sales, SUM(quantity) as units_sold')
            ->whereNotNull('product_id')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('product_id')
            ->orderByDesc('sales')
            ->limit(5)
            ->get()
            ->map(function (OrderItem $item) {
                $product = $item->product;

                return [
                    'product_id' => $item->product_id,
                    'name' => $product?->name,
                    'image' => $product?->images[0] ?? null,
                    'sales' => round((float) $item->sales, 2),
                    'units_sold' => (int) $item->units_sold,
                ];
            })
            ->values()
            ->all();
    }

    protected function recentOrders(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return Order::query()
            ->with('user:id,name,email')
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->user?->name,
                'total' => round((float) $order->total, 2),
                'order_status' => $order->order_status,
                'payment_status' => $order->payment_status,
                'created_at' => $order->created_at,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    protected function period(int $days): array
    {
        $end = CarbonImmutable::today();

        return ['start' => $end->subDays($days - 1)->startOfDay(), 'end' => $end->endOfDay()];
    }
}
