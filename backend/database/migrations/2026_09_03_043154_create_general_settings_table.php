<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name')->default('Waplia');
            $table->string('tagline')->nullable();
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('currency', 3)->default('INR');
            $table->string('timezone')->default('UTC');
            $table->timestamps();
        });

        DB::table('general_settings')->insert([
            'site_name' => 'Waplia',
            'tagline' => null,
            'logo' => null,
            'favicon' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'address' => null,
            'currency' => 'INR',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('general_settings');
    }
};
