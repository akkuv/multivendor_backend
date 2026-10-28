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
        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $table->index('product_variant_id');
        });

        Schema::table('vendor_order_items', function (Blueprint $table): void {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $table->index('product_variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropIndex(['product_variant_id']);
        });

        Schema::table('vendor_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropIndex(['product_variant_id']);
        });
    }
};
