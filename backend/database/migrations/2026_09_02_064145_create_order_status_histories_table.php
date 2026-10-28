<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->morphs('historable');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->nullableMorphs('actor');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('to_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
