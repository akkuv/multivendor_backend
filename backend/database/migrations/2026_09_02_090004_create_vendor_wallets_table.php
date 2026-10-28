<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendor_profiles')->cascadeOnDelete();
            $table->string('currency', 8)->default('INR');
            $table->decimal('available_balance', 12, 2)->default(0);
            $table->decimal('pending_balance', 12, 2)->default(0);
            $table->decimal('held_balance', 12, 2)->default(0);
            $table->decimal('paid_total', 12, 2)->default(0);
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();

            $table->unique('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_wallets');
    }
};
