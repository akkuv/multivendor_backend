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
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('fees_total', 12, 2)->default(0)->after('tax');
        });

        Schema::table('vendor_orders', function (Blueprint $table): void {
            $table->decimal('fees_total', 12, 2)->default(0)->after('tax');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('fees_total', 12, 2)->default(0)->after('total');
            $table->json('fees')->nullable()->after('fees_total');
        });

        Schema::table('vendor_order_items', function (Blueprint $table): void {
            $table->decimal('fees_total', 12, 2)->default(0)->after('total');
            $table->json('fees')->nullable()->after('fees_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_order_items', function (Blueprint $table): void {
            $table->dropColumn(['fees_total', 'fees']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['fees_total', 'fees']);
        });

        Schema::table('vendor_orders', function (Blueprint $table): void {
            $table->dropColumn('fees_total');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('fees_total');
        });
    }
};
