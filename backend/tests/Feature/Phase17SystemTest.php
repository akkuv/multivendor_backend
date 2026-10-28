<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\VendorStatus;
use App\Enums\VendorWalletTransactionType;
use App\Models\Category;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProfile;
use App\Notifications\AppNotification;
use App\Services\Auth\AuditLogService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Settings\EmailTemplateService;
use App\Services\Wallet\VendorWalletService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase17SystemTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------ //
    // Email Templates (admin)
    // ------------------------------------------------------------------ //

    public function test_admin_can_crud_email_templates(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/email-templates', [
            'key' => 'order_confirmed',
            'name' => 'Order Confirmed',
            'subject' => 'Your order {{order_number}} is confirmed',
            'body' => 'Hi {{customer_name}}, thanks for your {{order_number}}. Amount: {{amount}}',
        ])->assertCreated()
            ->assertJsonPath('data.key', 'order_confirmed');

        $this->getJson('/api/v1/admin/email-templates')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');

        $template = EmailTemplate::where('key', 'order_confirmed')->first();

        $this->putJson("/api/v1/admin/email-templates/{$template->id}", [
            'subject' => 'Updated subject',
        ])->assertOk()
            ->assertJsonPath('data.subject', 'Updated subject');

        $this->putJson("/api/v1/admin/email-templates/{$template->id}/toggle")->assertOk();
        $this->assertFalse($template->fresh()->is_active);

        $this->deleteJson("/api/v1/admin/email-templates/{$template->id}")->assertOk();
        $this->assertDatabaseMissing('email_templates', ['id' => $template->id]);
    }

    public function test_email_template_service_renders_variables(): void
    {
        EmailTemplate::create([
            'key' => 'welcome',
            'name' => 'Welcome',
            'subject' => 'Hello {{customer_name}}',
            'body' => 'Order {{order_number}} amount {{amount}} tracking {{tracking_number}} vendor {{vendor_name}}',
            'is_active' => true,
        ]);

        $rendered = app(EmailTemplateService::class)->render('welcome', [
            'customer_name' => 'Alice',
            'order_number' => 'ORD-1',
            'amount' => '100.00',
            'tracking_number' => 'TRK-1',
            'vendor_name' => 'Store',
        ]);

        $this->assertNotNull($rendered);
        $this->assertSame('Hello Alice', $rendered->subject);
        $this->assertStringContainsString('ORD-1', $rendered->body);
        $this->assertStringContainsString('Store', $rendered->body);
    }

    // ------------------------------------------------------------------ //
    // Notifications API
    // ------------------------------------------------------------------ //

    public function test_user_can_list_mark_read_and_delete_notifications(): void
    {
        $user = User::factory()->customer()->create();
        Sanctum::actingAs($user);

        $user->notify(new AppNotification(NotificationType::OrderCreated->value, 'Order', 'Order placed', ['order_id' => 1]));
        $user->notify(new AppNotification(NotificationType::WalletCredited->value, 'Wallet', 'Credited', ['amount' => 10]));

        $this->getJson('/api/v1/user/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data.data');

        $this->getJson('/api/v1/user/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 2);

        $notification = $user->notifications()->first();

        $this->putJson("/api/v1/user/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read_at', fn ($v) => $v !== null);

        $this->getJson('/api/v1/user/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 1);

        $this->postJson('/api/v1/user/notifications/read-all')->assertOk();
        $this->getJson('/api/v1/user/notifications/unread-count')->assertOk()->assertJsonPath('data.count', 0);

        $this->deleteJson("/api/v1/user/notifications/{$notification->id}")->assertOk();
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_email_templates_crud_requires_admin(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->getJson('/api/v1/admin/email-templates')->assertStatus(403);
    }

    // ------------------------------------------------------------------ //
    // Notification dispatcher wiring
    // ------------------------------------------------------------------ //

    public function test_vendor_approval_notifies_vendor(): void
    {
        Notification::fake();

        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $vendor = VendorProfile::factory()->create(['status' => VendorStatus::Pending->value, 'is_approved' => false]);

        $this->putJson("/api/v1/admin/vendors/{$vendor->id}/approve", ['status' => 'APPROVED'])->assertOk();

        Notification::assertSentTo($vendor->user, AppNotification::class, function ($notification) {
            return $notification->type === NotificationType::VendorApproved->value;
        });
    }

    public function test_vendor_payout_request_notifies_admins_immediately(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $vendorUser = User::factory()->vendor()->create();
        $vendor = VendorProfile::factory()->approved()->create(['user_id' => $vendorUser->id]);
        app(VendorWalletService::class)->creditAvailable($vendor, 5000, 'Test', VendorWalletTransactionType::Commission);
        Sanctum::actingAs($vendorUser);

        $this->postJson('/api/v1/vendor/payouts', [
            'amount' => 1000,
            'method' => 'bank_transfer',
            'account_details' => ['account_number' => '1234', 'ifsc' => 'TEST0001'],
        ])->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_vendor_product_submission_notifies_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->superAdmin()->create();
        $vendorUser = User::factory()->vendor()->create();
        VendorProfile::factory()->approved()->create(['user_id' => $vendorUser->id]);
        $category = Category::factory()->create();

        Sanctum::actingAs($vendorUser);

        $this->postJson('/api/v1/vendor/products', [
            'category_id' => $category->id,
            'name' => 'New submission',
            'price' => 100,
            'stock' => 10,
        ])->assertStatus(201);

        Notification::assertSentTo($admin, AppNotification::class, function ($notification) {
            return $notification->type === NotificationType::NewProductSubmitted->value;
        });
    }

    public function test_wallet_credit_notifies_customer(): void
    {
        Notification::fake();
        $user = User::factory()->customer()->create();

        app(WalletService::class)->credit($user, 500, 'Test');

        Notification::assertSentTo($user, AppNotification::class, function ($notification) {
            return $notification->type === NotificationType::WalletCredited->value;
        });
    }

    public function test_in_app_notification_is_written_synchronously_even_when_queue_is_down(): void
    {
        Queue::fake();

        $vendorUser = User::factory()->vendor()->create();

        app(NotificationDispatcher::class)->send(
            $vendorUser,
            NotificationType::NewOrderForVendor,
            'New order',
            'You have a new order',
            ['order_id' => 1],
        );

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $vendorUser->id,
            'notifiable_type' => User::class,
        ]);

        $row = DB::table('notifications')
            ->where('notifiable_id', $vendorUser->id)
            ->where('notifiable_type', User::class)
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(
            NotificationType::NewOrderForVendor->value,
            json_decode((string) $row->data, true)['type'],
        );
    }

    // ------------------------------------------------------------------ //
    // Reports
    // ------------------------------------------------------------------ //

    public function test_admin_sales_report_generates_summary(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $customer = User::factory()->customer()->create();
        $vendor = VendorProfile::factory()->approved()->create();

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'total' => 1000,
            'subtotal' => 900,
            'order_status' => 'DELIVERED',
            'payment_status' => 'PAID',
        ]);

        $order->items()->create([
            'vendor_id' => $vendor->id,
            'product_id' => Product::factory()->create(['vendor_id' => $vendor->id])->id,
            'product_name' => 'Widget',
            'unit_price' => 100,
            'quantity' => 9,
            'total' => 900,
            'commission_rate' => 10,
            'commission_amount' => 90,
            'status' => 'DELIVERED',
        ]);

        $response = $this->getJson('/api/v1/admin/reports/sales')
            ->assertOk()
            ->assertJsonPath('data.summary.total_orders', 1);

        $this->assertSame(1000, (int) $response->json('data.summary.total_revenue'));
    }

    public function test_admin_can_export_csv(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $response = $this->get('/api/v1/admin/reports/customers/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    public function test_vendor_report_is_scoped_to_own_vendor(): void
    {
        $vendorUser = User::factory()->vendor()->create();
        $vendor = VendorProfile::factory()->approved()->create(['user_id' => $vendorUser->id]);
        Sanctum::actingAs($vendorUser);

        $customer = User::factory()->customer()->create();
        $otherVendor = VendorProfile::factory()->approved()->create();

        $order = Order::factory()->create(['user_id' => $customer->id, 'total' => 500]);
        $order->items()->create([
            'vendor_id' => $otherVendor->id,
            'product_id' => Product::factory()->create(['vendor_id' => $otherVendor->id])->id,
            'product_name' => 'Other',
            'unit_price' => 500,
            'quantity' => 1,
            'total' => 500,
            'commission_rate' => 0,
            'commission_amount' => 0,
            'status' => 'PENDING',
        ]);

        $this->getJson('/api/v1/vendor/reports/orders')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_vendor_report_by_day_includes_daily_grouping_without_error(): void
    {
        $vendorUser = User::factory()->vendor()->create();
        $vendor = VendorProfile::factory()->approved()->create(['user_id' => $vendorUser->id]);
        Sanctum::actingAs($vendorUser);

        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create(['user_id' => $customer->id, 'total' => 500]);
        $order->items()->create([
            'vendor_id' => $vendor->id,
            'product_id' => Product::factory()->create(['vendor_id' => $vendor->id])->id,
            'product_name' => 'Gadget',
            'unit_price' => 500,
            'quantity' => 1,
            'total' => 500,
            'commission_rate' => 0,
            'commission_amount' => 0,
            'status' => 'DELIVERED',
        ]);

        $this->getJson('/api/v1/vendor/reports/sales?by_day=1')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonStructure(['data' => ['summary' => ['by_day']]]);

        $this->getJson('/api/v1/vendor/reports/orders?by_day=1')
            ->assertOk()
            ->assertJsonStructure(['data' => ['summary' => ['by_day']]]);

        $this->getJson('/api/v1/vendor/reports/returns?by_day=1')
            ->assertOk()
            ->assertJsonStructure(['data' => ['summary' => ['by_day']]]);
    }

    public function test_vendor_cannot_access_admin_only_report(): void
    {
        $vendorUser = User::factory()->vendor()->create();
        VendorProfile::factory()->approved()->create(['user_id' => $vendorUser->id]);
        Sanctum::actingAs($vendorUser);

        $this->getJson('/api/v1/vendor/reports/vendors')->assertStatus(422);
    }

    // ------------------------------------------------------------------ //
    // Audit logs
    // ------------------------------------------------------------------ //

    public function test_audit_log_service_records_module_and_changes(): void
    {
        $user = User::factory()->customer()->create();

        $log = app(AuditLogService::class)->log(
            $user->id,
            'product.approve',
            'Product approved',
            null,
            [],
            'product',
            null,
            ['approval_status' => 'PENDING'],
            ['approval_status' => 'APPROVED'],
        );

        $this->assertSame('product', $log->module);
        $this->assertSame('APPROVED', $log->new_data['approval_status']);
    }

    public function test_admin_can_view_audit_logs(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        app(AuditLogService::class)->log($admin->id, 'auth.login', 'User logged in.', null, [], 'auth');

        $this->getJson('/api/v1/admin/audit-logs')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.event', 'auth.login');
    }
}
