<?php

namespace Database\Seeders;

use App\Models\CategoryPage;
use App\Models\CategoryPageSection;
use App\Services\CategoryPageService;
use Illuminate\Database\Seeder;

class CategoryPageSectionSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(CategoryPageService::class);

        $pages = CategoryPage::query()
            ->where('is_active', true)
            ->with('category:id,name,image,description')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($pages as $page) {
            foreach ($service->defaultSectionDefinitions($page->category) as $definition) {
                CategoryPageSection::firstOrCreate(
                    [
                        'category_page_id' => $page->id,
                        'type' => $definition['type'],
                    ],
                    $definition,
                );
            }
        }
    }
}
