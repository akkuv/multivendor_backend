<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_page_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->json('config')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category_page_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_page_sections');
    }
};
