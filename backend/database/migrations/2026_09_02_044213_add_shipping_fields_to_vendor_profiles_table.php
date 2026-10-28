<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table): void {
            $table->decimal('shipping_charge', 12, 2)->default(50)->after('commission_rate');
            $table->decimal('free_shipping_threshold', 12, 2)->nullable()->after('shipping_charge');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table): void {
            $table->dropColumn(['shipping_charge', 'free_shipping_threshold']);
        });
    }
};
