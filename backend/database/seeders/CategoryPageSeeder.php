<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryPage;
use Illuminate\Database\Seeder;

class CategoryPageSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $sort = 0;

        foreach ($categories as $category) {
            CategoryPage::updateOrCreate(
                ['category_id' => $category->id],
                [
                    'slug' => $category->slug,
                    'title' => $category->name,
                    'sort_order' => $sort++,
                    'is_active' => true,
                ],
            );
        }
    }
}
