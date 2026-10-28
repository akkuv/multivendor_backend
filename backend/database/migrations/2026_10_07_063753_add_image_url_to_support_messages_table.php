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
        Schema::table('support_messages', function (Blueprint $table): void {
            // Absolute URL of an image attached to the message, set only when
            // the sender picked one. Uploads go through the shared images
            // endpoint, so messages keep a URL, never the bytes.
            $table->string('image_url', 2048)->nullable()->after('body');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table): void {
            $table->dropColumn('image_url');
        });
    }
};
