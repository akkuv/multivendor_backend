<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->json('images')->nullable()->after('body');
            $table->boolean('is_verified')->default(false)->after('is_approved');
            $table->boolean('is_hidden')->default(false)->after('is_verified');
            $table->foreignId('vendor_id')->nullable()->constrained('vendor_profiles')->nullOnDelete()->after('product_id');
            $table->timestamp('reported_at')->nullable()->after('is_hidden');
            $table->string('report_reason')->nullable()->after('reported_at');
            $table->index(['vendor_id', 'is_approved', 'is_hidden']);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn([
                'images',
                'is_verified',
                'is_hidden',
                'vendor_id',
                'reported_at',
                'report_reason',
            ]);
        });
    }
};
