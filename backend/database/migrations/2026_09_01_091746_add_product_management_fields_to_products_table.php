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
            $table->string('short_description')->nullable()->after('description');
            $table->json('specifications')->nullable()->after('short_description');
            $table->string('tax_class')->default('standard')->after('specifications');
            $table->decimal('gst', 5, 2)->nullable()->after('tax_class');
            $table->string('hsn_code')->nullable()->after('gst');
            $table->string('status')->default('DRAFT')->index()->after('is_active');
            $table->string('approval_status')->default('PENDING_APPROVAL')->index()->after('status');
            $table->boolean('featured')->default(false)->index()->after('approval_status');
            $table->boolean('best_seller')->default(false)->index()->after('featured');
            $table->string('meta_title')->nullable()->after('best_seller');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->string('meta_keywords')->nullable()->after('meta_description');
            $table->timestamp('approved_at')->nullable()->after('meta_keywords');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->string('rejection_reason')->nullable()->after('rejected_at');

            $table->index(['vendor_id', 'approval_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['vendor_id', 'approval_status']);

            $table->dropColumn([
                'short_description',
                'specifications',
                'tax_class',
                'gst',
                'hsn_code',
                'status',
                'approval_status',
                'featured',
                'best_seller',
                'meta_title',
                'meta_description',
                'meta_keywords',
                'approved_at',
                'rejected_at',
                'rejection_reason',
            ]);
        });
    }
};
