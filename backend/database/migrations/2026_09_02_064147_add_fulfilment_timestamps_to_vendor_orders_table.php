<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_orders', function (Blueprint $table): void {
            $table->timestamp('confirmed_at')->nullable()->after('status');
            $table->timestamp('packed_at')->nullable()->after('confirmed_at');
            $table->timestamp('ready_to_ship_at')->nullable()->after('packed_at');
            $table->timestamp('out_for_delivery_at')->nullable()->after('ready_to_ship_at');
            $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'confirmed_at',
                'packed_at',
                'ready_to_ship_at',
                'out_for_delivery_at',
                'cancelled_at',
            ]);
        });
    }
};
