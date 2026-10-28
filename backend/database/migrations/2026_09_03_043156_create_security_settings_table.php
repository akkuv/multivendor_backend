<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('login_attempt_limit')->default(5);
            $table->integer('lockout_minutes')->default(15);
            $table->integer('otp_expiry_minutes')->default(10);
            $table->integer('session_timeout_minutes')->default(60);
            $table->integer('remember_me_days')->default(30);
            $table->boolean('maintenance_mode')->default(false);
            $table->string('maintenance_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_settings');
    }
};
