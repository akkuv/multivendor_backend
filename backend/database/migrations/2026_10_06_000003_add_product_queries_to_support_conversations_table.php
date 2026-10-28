<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            // The product a customer is asking about; set only for product
            // queries. Deleting the product leaves the thread intact.
            $table->foreignId('product_id')->nullable()->after('order_id')
                ->constrained('products')->nullOnDelete();

            // Product-query lists filter by product, so the FK deserves its own
            // index.
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->dropIndex(['product_id']);
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
