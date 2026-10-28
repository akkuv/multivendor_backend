<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->string('razorpay_key_id')->nullable()->after('wallet_enabled');
            $table->text('razorpay_key_secret')->nullable()->after('razorpay_key_id');
            $table->text('razorpay_webhook_secret')->nullable()->after('razorpay_key_secret');
            $table->string('razorpay_mode', 20)->default('test')->after('razorpay_webhook_secret');
        });

        DB::table('payment_settings')
            ->whereNull('razorpay_mode')
            ->update(['razorpay_mode' => 'test']);
    }

    public function down(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->dropColumn(['razorpay_key_id', 'razorpay_key_secret', 'razorpay_webhook_secret', 'razorpay_mode']);
        });
    }
};
