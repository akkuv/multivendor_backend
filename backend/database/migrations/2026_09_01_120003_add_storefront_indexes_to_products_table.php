<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->index('brand_id', 'products_brand_id_idx');
            $table->index('price', 'products_price_idx');
            $table->index(['status', 'approval_status', 'is_active'], 'products_live_idx');
            $table->index(['brand_id', 'is_active'], 'products_brand_active_idx');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `products` ADD FULLTEXT INDEX `products_search_idx` (`name`, `short_description`, `description`, `sku`)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `products` DROP INDEX `products_search_idx`');
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_brand_active_idx');
            $table->dropIndex('products_live_idx');
            $table->dropIndex('products_price_idx');
            $table->dropIndex('products_brand_id_idx');
        });
    }
};
