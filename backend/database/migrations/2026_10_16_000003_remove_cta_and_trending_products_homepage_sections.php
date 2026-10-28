<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $types = ['CTA', 'TRENDING_PRODUCTS'];

        DB::table('homepage_sections')->whereIn('type', $types)->delete();

        if (Schema::hasTable('category_page_sections')) {
            DB::table('category_page_sections')->whereIn('type', $types)->delete();
        }
    }

    public function down(): void
    {
        //
    }
};
