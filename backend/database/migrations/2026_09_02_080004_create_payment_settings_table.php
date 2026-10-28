<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('razorpay_enabled')->default(true);
            $table->boolean('cod_enabled')->default(true);
            $table->boolean('wallet_enabled')->default(true);
            $table->decimal('cod_min_amount', 12, 2)->default(0);
            $table->decimal('cod_max_amount', 12, 2)->nullable();
            $table->decimal('cod_fee', 12, 2)->default(0);
            $table->string('currency')->default('INR');
            $table->timestamps();
        });

        DB::table('payment_settings')->insert([
            'razorpay_enabled' => true,
            'cod_enabled' => true,
            'wallet_enabled' => true,
            'cod_min_amount' => 0,
            'cod_max_amount' => null,
            'cod_fee' => 0,
            'currency' => 'INR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
