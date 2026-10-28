<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table): void {
            $table->decimal('price_at_add', 12, 2)->nullable()->after('product_id');
            $table->boolean('notify_price_drops')->default(false)->after('price_at_add');
            $table->boolean('notify_back_in_stock')->default(false)->after('notify_price_drops');
            $table->boolean('price_drop_notified')->default(false)->after('notify_back_in_stock');
            $table->boolean('back_in_stock_notified')->default(false)->after('price_drop_notified');
        });

        Schema::create('wishlist_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wishlist_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // price_drop | back_in_stock
            $table->decimal('old_price', 12, 2)->nullable();
            $table->decimal('new_price', 12, 2)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_notifications');

        Schema::table('wishlist_items', function (Blueprint $table): void {
            $table->dropColumn([
                'price_at_add',
                'notify_price_drops',
                'notify_back_in_stock',
                'price_drop_notified',
                'back_in_stock_notified',
            ]);
        });
    }
};
