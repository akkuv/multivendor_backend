<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The TRUST_BAR section type was removed from the application. Any existing
     * rows must go too: the model casts `type` to HomepageSectionType, so a
     * leftover row would throw when read.
     */
    public function up(): void
    {
        DB::table('homepage_sections')->where('type', 'TRUST_BAR')->delete();

        if (Schema::hasTable('category_page_sections')) {
            DB::table('category_page_sections')->where('type', 'TRUST_BAR')->delete();
        }
    }

    /**
     * The type no longer exists, so the removed rows cannot be restored.
     */
    public function down(): void
    {
        //
    }
};
