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
        Schema::create('shiprocket_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('environment', 20)->default('test');
            $table->string('base_url')->nullable();
            $table->string('email')->nullable();
            $table->text('password')->nullable();
            $table->text('token')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('default_pickup_location')->nullable();
            $table->json('pickup_locations')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shiprocket_settings');
    }
};
