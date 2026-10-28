<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A customer may request a return for the same order item again once the
     * previous return is closed (e.g. withdrawn). The service already guards
     * against active duplicates, so the unique index only ever blocked the
     * legitimate second request with a raw integrity error.
     */
    public function up(): void
    {
        Schema::table('product_returns', function (Blueprint $table) {
            // The FK on order_item_id needs an index to back it once the
            // unique index is gone.
            $table->index('order_item_id');
            $table->dropUnique('product_returns_order_item_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_returns', function (Blueprint $table) {
            $table->unique('order_item_id');
            $table->dropIndex('product_returns_order_item_id_index');
        });
    }
};
