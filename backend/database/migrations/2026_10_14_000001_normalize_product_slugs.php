<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Rewrite product slugs to be the human-readable product name only
     * (`orange-banarasi-corset-drape-skirt-set`), appending `-2`, `-3`, … only
     * when two products share a name. Previously every slug carried a random
     * six-character suffix.
     */
    public function up(): void
    {
        DB::table('products')
            ->orderBy('id')
            ->select(['id', 'name', 'slug'])
            ->chunkById(200, function ($products): void {
                foreach ($products as $product) {
                    $base = Str::slug((string) $product->name) ?: 'product';
                    $slug = $base;
                    $suffix = 2;

                    while (
                        DB::table('products')
                            ->where('slug', $slug)
                            ->where('id', '!=', $product->id)
                            ->exists()
                    ) {
                        $slug = $base.'-'.$suffix;
                        $suffix++;
                    }

                    if ($slug !== $product->slug) {
                        DB::table('products')
                            ->where('id', $product->id)
                            ->update(['slug' => $slug]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Slugs cannot be restored to their original random suffixes.
    }
};
