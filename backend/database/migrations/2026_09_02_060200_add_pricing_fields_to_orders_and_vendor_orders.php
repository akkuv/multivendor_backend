<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('cod_charge', 12, 2)->default(0)->after('shipping_charge');
            $table->decimal('wallet_amount', 12, 2)->default(0)->after('cod_charge');
        });

        Schema::table('vendor_orders', function (Blueprint $table): void {
            $table->decimal('cod_charge', 12, 2)->default(0)->after('shipping_charge');
            $table->decimal('wallet_amount', 12, 2)->default(0)->after('cod_charge');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_orders', function (Blueprint $table): void {
            $table->dropColumn(['wallet_amount', 'cod_charge']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['wallet_amount', 'cod_charge']);
        });
    }
};
