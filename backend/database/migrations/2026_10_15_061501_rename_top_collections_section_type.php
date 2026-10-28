<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The TOP_COLLECTIONS section type was renamed to FEATURED_CATEGORIES. The
     * stored value must follow: the model casts `type` to HomepageSectionType,
     * so a leftover row would throw when read.
     */
    public function up(): void
    {
        DB::table('homepage_sections')
            ->where('type', 'TOP_COLLECTIONS')
            ->update(['type' => 'FEATURED_CATEGORIES']);

        if (Schema::hasTable('category_page_sections')) {
            DB::table('category_page_sections')
                ->where('type', 'TOP_COLLECTIONS')
                ->update(['type' => 'FEATURED_CATEGORIES']);
        }
    }

    public function down(): void
    {
        DB::table('homepage_sections')
            ->where('type', 'FEATURED_CATEGORIES')
            ->update(['type' => 'TOP_COLLECTIONS']);

        if (Schema::hasTable('category_page_sections')) {
            DB::table('category_page_sections')
                ->where('type', 'FEATURED_CATEGORIES')
                ->update(['type' => 'TOP_COLLECTIONS']);
        }
    }
};
