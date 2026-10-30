<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->string('status', 16)->default('active')->after('price_at_add');
            $table->timestamp('removed_at')->nullable()->after('status');
            $table->index(['user_id', 'status']);
            $table->index(['product_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['product_id', 'status']);
            $table->dropIndex(['created_at']);
            $table->dropColumn(['status', 'removed_at']);
        });
    }
};