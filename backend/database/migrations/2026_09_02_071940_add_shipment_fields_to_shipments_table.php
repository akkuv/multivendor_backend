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
        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedBigInteger('shiprocket_order_id')->nullable()->after('order_id')->index();
            $table->unsignedBigInteger('shipment_id')->nullable()->after('shiprocket_order_id')->index();
            $table->string('picker')->nullable()->after('courier_name');
            $table->unsignedBigInteger('courier_id')->nullable()->after('courier_name');
            $table->string('tracking_url')->nullable()->after('tracking_number');
            $table->string('pickup_location')->nullable()->after('tracking_url');
            $table->string('pickup_status', 30)->default('NONE')->after('pickup_location')->index();
            $table->string('label_url')->nullable()->after('pickup_status');
            $table->string('manifest_url')->nullable()->after('label_url');
            $table->string('last_event')->nullable()->after('status');
            $table->text('error_message')->nullable()->after('manifest_url');
            $table->string('external_status')->nullable()->after('last_event');
            $table->unsignedInteger('retry_count')->default(0)->after('error_message');
            $table->timestamp('pickup_requested_at')->nullable()->after('shipped_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'shiprocket_order_id',
                'shipment_id',
                'picker',
                'courier_id',
                'tracking_url',
                'pickup_location',
                'pickup_status',
                'label_url',
                'manifest_url',
                'last_event',
                'error_message',
                'external_status',
                'retry_count',
                'pickup_requested_at',
            ]);
        });
    }
};
