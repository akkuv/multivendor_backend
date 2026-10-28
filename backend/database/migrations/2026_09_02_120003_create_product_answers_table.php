<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendor_profiles')->cascadeOnDelete();
            $table->string('answer', 2000);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->index(['product_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_answers');
    }
};
