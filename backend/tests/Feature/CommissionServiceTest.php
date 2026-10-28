<?php

namespace Tests\Feature;

use App\Enums\CommissionLevel;
use App\Models\CommissionRule;
use App\Models\CommissionSetting;
use App\Models\Product;
use App\Models\VendorProfile;
use App\Services\Commission\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function defaultVendor(): VendorProfile
    {
        return VendorProfile::factory()->create([
            'commission_rate' => (float) config('commission.default_rate', 10),
        ]);
    }

    private function product(): Product
    {
        return Product::factory()->create();
    }

    private function service(): CommissionService
    {
        return app(CommissionService::class);
    }

    public function test_category_rule_applies_when_vendor_rate_is_the_default(): void
    {
        $vendor = $this->defaultVendor();
        $product = $this->product();

        CommissionRule::factory()->category($product->category_id, 5)->create();

        $resolution = $this->service()->ruleFor($vendor, $product);

        $this->assertSame(5.0, $resolution['rate']);
        $this->assertSame(CommissionLevel::Category, $resolution['level']);
    }

    public function test_explicit_vendor_rate_overrides_category_rule(): void
    {
        $vendor = VendorProfile::factory()->create(['commission_rate' => 15]);
        $product = $this->product();

        CommissionRule::factory()->category($product->category_id, 5)->create();

        $resolution = $this->service()->ruleFor($vendor, $product);

        $this->assertSame(15.0, $resolution['rate']);
        $this->assertSame(CommissionLevel::Vendor, $resolution['level']);
    }

    public function test_vendor_rule_beats_profile_rate(): void
    {
        $vendor = VendorProfile::factory()->create(['commission_rate' => 15]);
        $product = $this->product();

        CommissionRule::factory()->vendor($vendor->id, 7)->create();

        $resolution = $this->service()->ruleFor($vendor, $product);

        $this->assertSame(7.0, $resolution['rate']);
        $this->assertSame(CommissionLevel::Vendor, $resolution['level']);
        $this->assertNotNull($resolution['rule_id']);
    }

    public function test_product_rule_wins_over_all_other_levels(): void
    {
        $vendor = VendorProfile::factory()->create(['commission_rate' => 15]);
        $product = $this->product();

        CommissionRule::factory()->product($product->id, 4)->create();
        CommissionRule::factory()->category($product->category_id, 5)->create();

        $resolution = $this->service()->ruleFor($vendor, $product);

        $this->assertSame(4.0, $resolution['rate']);
        $this->assertSame(CommissionLevel::Product, $resolution['level']);
    }

    public function test_global_setting_applies_when_no_deeper_rule_exists(): void
    {
        $vendor = $this->defaultVendor();
        $product = $this->product();

        CommissionSetting::factory()->create(['name' => 'default', 'rate' => 8]);
        CommissionSetting::factory()->create(['name' => 'legacy', 'rate' => 2, 'is_active' => false]);

        $resolution = $this->service()->ruleFor($vendor, $product);

        $this->assertSame(8.0, $resolution['rate']);
        $this->assertSame(CommissionLevel::Global, $resolution['level']);
    }

    public function test_global_rule_beats_global_setting(): void
    {
        $vendor = $this->defaultVendor();
        $product = $this->product();

        CommissionRule::factory()->global(6)->create();
        CommissionSetting::factory()->create(['name' => 'default', 'rate' => 8]);

        $resolution = $this->service()->ruleFor($vendor, $product);

        $this->assertSame(6.0, $resolution['rate']);
        $this->assertSame(CommissionLevel::Global, $resolution['level']);
    }

    public function test_config_default_is_final_fallback(): void
    {
        $vendor = $this->defaultVendor();
        $product = $this->product();

        $resolution = $this->service()->ruleFor($vendor, $product);

        $this->assertSame((float) config('commission.default_rate', 10), $resolution['rate']);
        $this->assertSame(CommissionLevel::Global, $resolution['level']);
    }

    public function test_calculate_for_uses_category_rate_and_preserves_pricing(): void
    {
        $vendor = $this->defaultVendor();
        $product = $this->product();

        CommissionRule::factory()->category($product->category_id, 20)->create();

        $result = $this->service()->calculateFor($vendor, $product, 1000.0);

        $this->assertSame(20.0, $result['rate']);
        $this->assertSame(200.0, $result['commission_amount']);
        $this->assertSame(800.0, $result['net_amount']);
        $this->assertSame(CommissionLevel::Category->value, $result['level']);
    }

    public function test_commission_rate_for_falls_back_to_global_setting(): void
    {
        $vendor = $this->defaultVendor();

        CommissionSetting::factory()->create(['name' => 'default', 'rate' => 8]);

        $this->assertSame(8.0, $this->service()->commissionRateFor($vendor));
    }

    public function test_commission_rate_for_uses_explicit_override(): void
    {
        $vendor = VendorProfile::factory()->create(['commission_rate' => 12]);

        CommissionSetting::factory()->create(['name' => 'default', 'rate' => 8]);

        $this->assertSame(12.0, $this->service()->commissionRateFor($vendor));
    }
}
