<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropForeign(['order_id']);
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropUnique('shipments_order_id_unique');
            $table->foreignId('vendor_order_id')->nullable()->after('order_id')->unique()->constrained('vendor_orders')->nullOnDelete();
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropForeign(['order_id']);
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropForeign(['vendor_order_id']);
            $table->dropUnique(['vendor_order_id']);
            $table->unique('order_id');
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }
};
