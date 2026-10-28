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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku')->nullable()->index();
            $table->string('barcode')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('mrp', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('discount', 5, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->integer('reserved_stock')->default(0);
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->string('status')->default('ACTIVE')->index();
            $table->json('attributes')->nullable();
            $table->string('combination_hash', 64);
            $table->json('images')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['product_id', 'combination_hash']);
            $table->index(['product_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
