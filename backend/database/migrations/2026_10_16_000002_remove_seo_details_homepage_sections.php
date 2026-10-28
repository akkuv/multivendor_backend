<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('homepage_sections')->where('type', 'SEO_DETAILS')->delete();

        if (Schema::hasTable('category_page_sections')) {
            DB::table('category_page_sections')->where('type', 'SEO_DETAILS')->delete();
        }
    }

    public function down(): void
    {
        //
    }
};
