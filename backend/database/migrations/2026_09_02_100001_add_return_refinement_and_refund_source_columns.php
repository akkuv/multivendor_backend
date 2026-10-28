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
        Schema::table('product_returns', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('order_item_id');
            $table->json('images')->nullable()->after('description');
            $table->string('status_notes', 1000)->nullable()->after('status');
            $table->timestamp('pickup_scheduled_at')->nullable()->after('pickup_address_id');
            $table->timestamp('inspecting_at')->nullable()->after('received_at');
            $table->timestamp('approved_for_refund_at')->nullable()->after('inspecting_at');
            $table->timestamp('closed_at')->nullable()->after('refunded_at');
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('amount');
            $table->string('method')->nullable()->index()->after('status');
            $table->decimal('wallet_amount', 12, 2)->default(0)->after('method');
            $table->decimal('gateway_amount', 12, 2)->default(0)->after('wallet_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_returns', function (Blueprint $table) {
            $table->dropColumn([
                'quantity',
                'images',
                'status_notes',
                'pickup_scheduled_at',
                'inspecting_at',
                'approved_for_refund_at',
                'closed_at',
            ]);
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'method', 'wallet_amount', 'gateway_amount']);
        });
    }
};
