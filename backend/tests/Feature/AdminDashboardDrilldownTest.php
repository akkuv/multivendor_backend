<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ProductApprovalStatus;
use App\Enums\ReturnStatus;
use App\Enums\VendorCommissionStatus;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\Refund;
use App\Models\User;
use App\Models\VendorCommission;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A dashboard stat card links into a list carrying the same filters the stat was
 * counted under. These tests pin that contract: the row count a filtered list
 * returns must match the number the dashboard reports for the same window.
 */
class AdminDashboardDrilldownTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    /**
     * @return array<int, int>
     */
    private function idsFrom(string $responseKey, string $path, array $query = []): array
    {
        $url = '/api/v1/admin'.$path.'?'.http_build_query($query);

        return collect($this->getJson($url)->assertOk()->json('data.data'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function test_pending_products_card_matches_the_pending_products_list(): void
    {
        $this->actingAsAdmin();
        $vendor = VendorProfile::factory()->approved()->create();
        Product::factory()->count(2)->pending()->create(['vendor_id' => $vendor->id]);
        Product::factory()->count(3)->create(['vendor_id' => $vendor->id]);

        $pending = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data.pending_products');
        $this->assertSame(2, $pending);

        $listed = $this->idsFrom('/products', '/products', ['approval_status' => ProductApprovalStatus::PendingApproval->value]);
        $this->assertCount($pending, $listed);
    }

    public function test_products_can_be_narrowed_to_a_category(): void
    {
        $this->actingAsAdmin();
        $vendor = VendorProfile::factory()->approved()->create();
        $category = Category::factory()->create();
        $other = Category::factory()->create();

        $inCategory = Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $category->id]);
        Product::factory()->create(['vendor_id' => $vendor->id, 'category_id' => $other->id]);

        $listed = $this->idsFrom('/products', '/products', ['category_id' => $category->id]);
        $this->assertSame([$inCategory->id], $listed);
    }

    public function test_new_customers_card_matches_a_created_days_filtered_user_list(): void
    {
        $this->actingAsAdmin();

        $recent = User::factory()->customer()->create();
        User::factory()->customer()->create(['created_at' => now()->subDays(40)]);
        // A vendor in the same window must not leak into a customer-scoped card.
        User::factory()->vendor()->create();

        $count = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data.customer_count');
        $this->assertSame(1, $count);

        $listed = $this->idsFrom('/users', '/users', ['role' => 'CUSTOMER', 'created_days' => 30]);
        $this->assertCount($count, $listed);
        $this->assertContains($recent->id, $listed);
    }

    public function test_active_and_pending_vendor_cards_match_their_lists(): void
    {
        $this->actingAsAdmin();
        VendorProfile::factory()->count(2)->approved()->create();
        VendorProfile::factory()->count(3)->pending()->create();

        $dashboard = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');

        $active = $this->idsFrom('/vendors', '/vendors', ['status' => VendorStatus::Approved->value]);
        $pending = $this->idsFrom('/vendors', '/vendors', ['status' => VendorStatus::Pending->value]);

        $this->assertCount($dashboard['active_vendors'], $active);
        $this->assertCount($dashboard['pending_vendors'], $pending);
        $this->assertEmpty(array_intersect($active, $pending));
    }

    public function test_todays_orders_card_matches_a_single_day_order_list(): void
    {
        $this->actingAsAdmin();

        $today = Order::factory()->count(2)->create();
        Order::factory()->create(['created_at' => now()->subDays(3)]);

        $count = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data.today_orders');
        $this->assertSame(2, $count);

        $listed = $this->idsFrom('/orders', '/orders', ['created_days' => 1]);
        $this->assertCount($count, $listed);
        foreach ($today->pluck('id')->all() as $id) {
            $this->assertContains($id, $listed);
        }
    }

    public function test_revenue_card_matches_paid_orders_in_range(): void
    {
        $this->actingAsAdmin();

        Order::factory()->count(2)->create(['payment_status' => PaymentStatus::Paid->value, 'total' => 1000]);
        Order::factory()->create(['payment_status' => PaymentStatus::Pending->value, 'total' => 9999]);
        Order::factory()->create([
            'payment_status' => PaymentStatus::Paid->value,
            'total' => 5000,
            'created_at' => now()->subDays(40),
        ]);

        $revenue = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data.revenue');
        $this->assertEqualsWithDelta(2000, $revenue, 0.01);

        $listed = $this->idsFrom('/orders', '/orders', ['payment_status' => 'PAID', 'created_days' => 30]);
        $this->assertCount(2, $listed);
    }

    public function test_orders_can_be_filtered_to_a_single_day_from_a_chart_point(): void
    {
        $this->actingAsAdmin();

        $day = Carbon::today()->subDays(2);
        $onThatDay = Order::factory()->create(['created_at' => $day->copy()->setTime(10, 0)]);
        Order::factory()->create(['created_at' => $day->copy()->addDay()->setTime(10, 0)]);
        Order::factory()->create();

        $listed = $this->idsFrom('/orders', '/orders', ['created_from' => $day->toDateString()]);

        $this->assertSame([$onThatDay->id], $listed);
    }

    public function test_an_explicit_created_range_wins_over_created_days(): void
    {
        $this->actingAsAdmin();

        $yesterday = Order::factory()->create(['created_at' => now()->subDay()]);
        Order::factory()->create();

        $listed = $this->idsFrom('/orders', '/orders', [
            'created_days' => 30,
            'created_from' => Carbon::yesterday()->toDateString(),
        ]);

        $this->assertSame([$yesterday->id], $listed);
    }

    public function test_an_absurd_created_days_value_is_clamped_and_still_matches_today(): void
    {
        $this->actingAsAdmin();

        Order::factory()->create();
        Order::factory()->create(['created_at' => now()->subDays(400)]);

        $listed = $this->idsFrom('/orders', '/orders', ['created_days' => 99999]);

        $this->assertCount(1, $listed);
    }

    public function test_returns_endpoint_returns_a_per_status_breakdown(): void
    {
        $this->actingAsAdmin();

        ProductReturn::factory()->count(2)->create(['status' => ReturnStatus::Requested->value]);
        ProductReturn::factory()->create(['status' => ReturnStatus::Refunded->value]);

        $response = $this->getJson('/api/v1/admin/returns')->assertOk();

        $response->assertJsonPath('data.in_range_total', 3)
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.refunded', 1)
            ->assertJsonPath('data.by_status.REQUESTED', 2)
            ->assertJsonPath('data.by_status.REFUNDED', 1);
    }

    public function test_returns_status_filter_reports_the_filtered_page_total(): void
    {
        $this->actingAsAdmin();

        ProductReturn::factory()->count(2)->create(['status' => ReturnStatus::Requested->value]);
        ProductReturn::factory()->create(['status' => ReturnStatus::Refunded->value]);

        // The breakdown deliberately ignores the status filter so the cards stay
        // whole, but it must not overwrite the paginator's own `total`.
        $this->getJson('/api/v1/admin/returns?status=REFUNDED')
            ->assertOk()
            ->assertJsonPath('data.in_range_total', 3)
            ->assertJsonPath('data.total', 1)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_returns_breakdown_ignores_the_status_filter_so_the_cards_stay_whole(): void
    {
        $this->actingAsAdmin();

        ProductReturn::factory()->count(2)->create(['status' => ReturnStatus::Requested->value]);
        ProductReturn::factory()->create(['status' => ReturnStatus::Refunded->value]);

        $this->getJson('/api/v1/admin/returns?status=REFUNDED')
            ->assertOk()
            ->assertJsonPath('data.in_range_total', 3)
            ->assertJsonPath('data.by_status.REQUESTED', 2)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_returns_breakdown_respects_the_created_range(): void
    {
        $this->actingAsAdmin();

        ProductReturn::factory()->create();
        ProductReturn::factory()->create(['created_at' => now()->subDays(40)]);

        $this->getJson('/api/v1/admin/returns?created_days=30')
            ->assertOk()
            ->assertJsonPath('data.in_range_total', 1)
            ->assertJsonPath('data.total', 1);
    }

    public function test_refunds_can_be_filtered_by_created_range(): void
    {
        $this->actingAsAdmin();

        Refund::factory()->count(2)->create();
        Refund::factory()->create(['created_at' => now()->subDays(40)]);

        $this->getJson('/api/v1/admin/refunds?created_days=30')
            ->assertOk()
            ->assertJsonCount(2, 'data.data');
    }

    public function test_commission_ledger_returns_a_summary_next_to_the_page(): void
    {
        $this->actingAsAdmin();

        VendorCommission::factory()->settled(1000, 10)->create();
        VendorCommission::factory()->settled(2000, 20)->create([
            'status' => VendorCommissionStatus::Pending->value,
            'settled_at' => null,
        ]);

        $this->getJson('/api/v1/admin/settlements')
            ->assertOk()
            ->assertJsonPath('data.summary.total_commission', 500)
            ->assertJsonPath('data.summary.total_net_payable', 2500)
            ->assertJsonPath('data.summary.pending', 1)
            ->assertJsonPath('data.summary.pending_amount', 1600)
            ->assertJsonCount(2, 'data.commissions.data');
    }

    public function test_commission_card_counts_the_same_ledger_rows_its_link_lists(): void
    {
        $this->actingAsAdmin();

        VendorCommission::factory()->settled(1000, 10)->create();
        VendorCommission::factory()->settled(500, 5)->create([
            'created_at' => now()->subDays(40),
        ]);

        // 1000 * 10% = 100 commission on the in-window row; the 40-day-old row
        // sits outside the 30-day window and must not be counted.

        $dashboard = $this->getJson('/api/v1/admin/dashboard?days=30')->assertOk()->json('data');

        // The "Commission Earned" card is range-scoped; the ledger link carries
        // `created_days=30`, so the two have to agree on the same window.
        $listed = $this->getJson('/api/v1/admin/settlements?created_days=30')
            ->assertOk()
            ->json('data.commissions.data');

        $this->assertEqualsWithDelta(100.0, (float) $dashboard['total_commission'], 0.01);
        $this->assertCount(1, $listed);
        $this->assertEqualsWithDelta(
            (float) $dashboard['total_commission'],
            (float) collect($listed)->sum('commission_amount'),
            0.01,
        );
    }

    public function test_vendor_payable_card_matches_the_settled_commission_list(): void
    {
        $this->actingAsAdmin();

        VendorCommission::factory()->settled(1000, 10)->create();
        VendorCommission::factory()->settled(800, 20)->create([
            'status' => VendorCommissionStatus::Pending->value,
            'settled_at' => null,
        ]);

        $payable = (float) $this->getJson('/api/v1/admin/dashboard?days=30')
            ->assertOk()
            ->json('data.vendor_payable');

        $listed = $this->getJson('/api/v1/admin/settlements?status=SETTLED')
            ->assertOk()
            ->json('data.commissions.data');

        // Pending rows are excluded from the payable figure, so the drill-down
        // list must be the settled subset.
        $this->assertEqualsWithDelta(900.0, $payable, 0.01);
        $this->assertCount(1, $listed);
        $this->assertEqualsWithDelta($payable, (float) collect($listed)->sum('net_payable'), 0.01);
    }

    public function test_commission_ledger_can_be_filtered_by_status_and_range(): void
    {
        $this->actingAsAdmin();

        VendorCommission::factory()->settled()->create([
            'status' => VendorCommissionStatus::Pending->value,
            'settled_at' => null,
        ]);
        VendorCommission::factory()->settled()->create([
            'status' => VendorCommissionStatus::Pending->value,
            'settled_at' => null,
            'created_at' => now()->subDays(40),
        ]);
        VendorCommission::factory()->settled()->create();

        $this->getJson('/api/v1/admin/settlements?status=PENDING&created_days=30')
            ->assertOk()
            ->assertJsonCount(1, 'data.commissions.data');
    }

    public function test_payout_requests_can_be_filtered_by_status_and_range(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/payouts?status=PENDING&created_days=30')
            ->assertOk()
            ->assertJsonStructure(['data' => ['payouts' => ['data', 'current_page', 'last_page', 'total']]]);
    }
}
