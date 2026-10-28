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
        Schema::create('shipment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_id')->nullable()->index();
            $table->string('awb', 64)->nullable()->index();
            $table->string('event', 64)->index();
            $table->json('payload');
            $table->string('source_ip')->nullable();
            $table->boolean('verified')->default(false);
            $table->string('status', 30)->default('RECEIVED')->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_webhooks');
    }
};
