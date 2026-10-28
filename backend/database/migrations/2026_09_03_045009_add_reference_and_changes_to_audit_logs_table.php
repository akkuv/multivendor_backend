<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('module', 50)->nullable()->after('event')->index();
            $table->string('reference_type', 100)->nullable()->after('module');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            $table->json('old_data')->nullable()->after('meta');
            $table->json('new_data')->nullable()->after('old_data');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['module', 'reference_type', 'reference_id', 'old_data', 'new_data']);
        });
    }
};
