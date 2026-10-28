<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('question', 1000);
            $table->boolean('is_answered')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('reported_at')->nullable();
            $table->string('report_reason')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'is_hidden', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_questions');
    }
};
