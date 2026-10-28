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
        // The product form (and Store/UpdateProductRequest) allow up to 500
        // characters, but the column was a VARCHAR(255): anything longer blew up
        // with SQLSTATE[22001] "Data too long for column 'short_description'".
        Schema::table('products', function (Blueprint $table) {
            $table->text('short_description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('short_description')->nullable()->change();
        });
    }
};
