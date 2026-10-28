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
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->decimal('balance_before', 12, 2)->default(0)->after('amount');
            $table->string('status')->default('COMPLETED')->index()->after('balance_after');

            $table->index(['wallet_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex(['wallet_id', 'status']);
            $table->dropColumn(['balance_before', 'status']);
        });
    }
};
