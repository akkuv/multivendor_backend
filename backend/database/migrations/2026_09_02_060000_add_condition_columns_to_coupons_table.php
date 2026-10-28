<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->unsignedInteger('per_user_limit')->nullable()->default(1)->after('usage_limit');
            $table->boolean('first_order_only')->default(false)->after('per_user_limit');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropColumn(['per_user_limit', 'first_order_only']);
        });
    }
};
