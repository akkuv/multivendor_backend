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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status_notes', 500)->nullable()->after('notes');
        });

        Schema::table('vendor_orders', function (Blueprint $table) {
            $table->string('status_notes', 500)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_orders', function (Blueprint $table) {
            $table->dropColumn('status_notes');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('status_notes');
        });
    }
};
