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
        Schema::table('refunds', function (Blueprint $table) {
            $table->foreignId('vendor_order_id')
                ->nullable()
                ->after('order_item_id')
                ->unique()
                ->constrained('vendor_orders')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropForeign(['vendor_order_id']);
            $table->dropUnique(['vendor_order_id']);
            $table->dropColumn('vendor_order_id');
        });
    }
};
