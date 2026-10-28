<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendor_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 255)->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_verified')->default(true);
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('reported_at')->nullable();
            $table->string('report_reason')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'user_id']);
            $table->index(['vendor_id', 'is_approved', 'is_hidden', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_reviews');
    }
};
