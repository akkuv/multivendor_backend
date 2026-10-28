<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_sections', function (Blueprint $table) {
            // Null keeps the section on the storefront homepage; a value scopes it
            // to one admin-created page so both share the exact same section engine.
            $table->foreignId('custom_page_id')
                ->nullable()
                ->after('id')
                ->constrained('custom_pages')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('homepage_sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('custom_page_id');
        });
    }
};
