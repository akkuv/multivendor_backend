<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CommissionSettingSeeder::class,
            SuperAdminSeeder::class,
            AttributeSeeder::class,
            AttributeValueSeeder::class,
            ColorSeeder::class,
            SizeGroupSeeder::class,
            SizeSeeder::class,
            VendorSeeder::class,
            CustomerSeeder::class,
            VendorReviewSeeder::class,
        ]);

        $this->call(KalkiCatalogSeeder::class);

        $this->call(ReviewDemoSeeder::class);
        $this->call(ProductQuestionDemoSeeder::class);
        $this->call(CouponSeeder::class);
        $this->call(VendorReturnDemoSeeder::class);
        $this->call(VendorShipmentDemoSeeder::class);
        $this->call(CoPurchaseDemoSeeder::class);
        $this->call(HomepageSeeder::class);
        $this->call(CategoryPageSeeder::class);
        $this->call(CategoryPageSectionSeeder::class);
    }
}
