<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\VendorOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendorProfile;

        if (! $vendor) {
            return $this->error(__('Vendor profile not found.'), null, 404);
        }

        $productCount = Product::where('vendor_id', $vendor->id)->count();
        $orderCount = VendorOrder::where('vendor_id', $vendor->id)->count();
        $pendingOrders = VendorOrder::where('vendor_id', $vendor->id)
            ->where('status', 'PENDING')
            ->count();

        $revenue = VendorOrder::where('vendor_id', $vendor->id)
            ->whereHas('order', fn ($q) => $q->where('payment_status', 'PAID'))
            ->sum('total');

        $walletBalance = $vendor->wallet?->totalBalance() ?? 0;

        $recentOrders = VendorOrder::where('vendor_id', $vendor->id)
            ->with('order.user')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return $this->success(__('Dashboard data.'), [
            'product_count' => $productCount,
            'order_count' => $orderCount,
            'pending_orders' => $pendingOrders,
            'revenue' => round((float) $revenue, 2),
            'wallet_balance' => $walletBalance,
            'recent_orders' => $recentOrders,
        ]);
    }
}
