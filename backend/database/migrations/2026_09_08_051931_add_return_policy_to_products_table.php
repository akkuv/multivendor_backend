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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_returnable')->default(true)->after('is_active');
            $table->unsignedInteger('return_days')->nullable()->after('is_returnable');
            $table->text('return_policy')->nullable()->after('return_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_returnable', 'return_days', 'return_policy']);
        });
    }
};
