<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->string('currency', 3)->default('INR')->after('user_id');
            $table->decimal('available_balance', 12, 2)->default(0)->after('currency');
            $table->decimal('pending_balance', 12, 2)->default(0)->after('available_balance');
            $table->string('status')->default('ACTIVE')->index()->after('pending_balance');
        });

        DB::table('wallets')->update([
            'available_balance' => DB::raw('balance'),
        ]);

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('balance', 12, 2)->default(0)->after('status');
        });

        DB::table('wallets')->update([
            'balance' => DB::raw('available_balance'),
        ]);

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn(['available_balance', 'pending_balance', 'status', 'currency']);
        });
    }
};
