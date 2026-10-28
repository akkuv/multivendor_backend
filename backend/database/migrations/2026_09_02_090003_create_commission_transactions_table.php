<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendor_profiles')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_commission_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('rate', 5, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('type', 20)->default('CHARGE');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_transactions');
    }
};
