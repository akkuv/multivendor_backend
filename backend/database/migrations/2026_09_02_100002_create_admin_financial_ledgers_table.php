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
        Schema::create('admin_financial_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->index();
            $table->foreignId('commission_id')->nullable()->index();
            $table->foreignId('vendor_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_financial_ledgers');
    }
};
