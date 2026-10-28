<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('level', 20)->index();
            $table->unsignedBigInteger('scope_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->decimal('rate', 8, 2);
            $table->string('type', 20)->default('PERCENTAGE');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['level', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
