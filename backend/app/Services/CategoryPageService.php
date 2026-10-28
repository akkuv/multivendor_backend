<?php

namespace App\Services;

use App\Enums\HomepageSectionType;
use App\Http\Resources\V1\CategoryResource;
use App\Models\Category;
use App\Models\CategoryPage;
use App\Models\CategoryPageSection;
use Illuminate\Support\Collection;

class CategoryPageService
{
    public function build(Category $category, ?CategoryPage $custom = null): array
    {
        $sections = $this->sectionsFor($category, $custom);

        $page = $custom ?? $this->defaultPage($category);
        $isCustom = $custom !== null;

        return [
            'page' => [
                'id' => $page->id,
                'category' => new CategoryResource($category),
                'slug' => $page->slug,
                'title' => $page->title,
                'subtitle' => $page->subtitle,
                'description' => $page->description,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'is_active' => true,
                'is_custom' => $isCustom,
            ],
            'listing' => $this->listingConfig($category, $page, $isCustom),
            'sections' => $sections
                ->map(fn (CategoryPageSection $section) => $this->resolveSection($category, $section))
                ->values()
                ->all(),
        ];
    }

    /** @return Collection<int, CategoryPageSection> */
    protected function sectionsFor(Category $category, ?CategoryPage $custom): Collection
    {
        if ($custom !== null) {
            $sections = $custom->sections()->where('is_active', true)->get();
            if ($sections->isNotEmpty()) {
                return $sections;
            }
        }

        $defaults = collect($this->defaultSectionDefinitions($category));

        return $defaults->map(function (array $data) use ($category): CategoryPageSection {
            $section = new CategoryPageSection;
            $section->forceFill($data)->categoryPage()->associate($category->id);

            return $section;
        });
    }

    /**
     * The default sections a category page renders when it has no saved sections.
     * Used both at runtime and by the seeder to materialize editable rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function defaultSectionDefinitions(Category $category): array
    {
        $hasChildren = $category->children()->where('is_active', true)->exists();

        return [
            [
                'type' => HomepageSectionType::Hero->value,
                'title' => $category->name,
                'subtitle' => $category->description,
                'config' => ['image' => $category->image],
                'sort_order' => 0,
            ],
            [
                'type' => HomepageSectionType::Categories->value,
                'title' => $hasChildren ? 'Browse Subcategories' : 'Related Categories',
                'subtitle' => $hasChildren ? 'Take your pick below' : 'Explore similar categories',
                'config' => ['limit' => 12],
                'sort_order' => 1,
            ],
            [
                'type' => HomepageSectionType::BestSellers->value,
                'title' => 'Best Sellers in '.$category->name,
                'subtitle' => 'Popular picks',
                'config' => ['limit' => 10],
                'sort_order' => 4,
            ],
            [
                'type' => HomepageSectionType::Testimonials->value,
                'title' => 'What Our Customers Say',
                'subtitle' => null,
                'config' => ['limit' => 6],
                'sort_order' => 6,
            ],
        ];
    }

    protected function defaultPage(Category $category): CategoryPage
    {
        $page = new CategoryPage;
        $page->forceFill([
            'category_id' => $category->id,
            'slug' => $category->slug,
            'title' => $category->name,
            'subtitle' => null,
            'is_active' => true,
        ]);

        return $page;
    }

    /**
     * Where the product grid and its "load more" links point. A page rendered
     * from real stored sections has the full section flow (the grid alone comes
     * from the product scope endpoints); one falling back to the built-in
     * defaults scopes the grid to this category.
     */
    protected function listingConfig(Category $category, CategoryPage $page, bool $isCustom): array
    {
        if ($isCustom && $page->sections()->where('is_active', true)->exists()) {
            return [
                'scope_key' => 'category_id',
                'scope_value' => $category->id,
                'scope_url' => '/shop?category_id='.$category->id,
            ];
        }

        return [
            'scope_key' => 'category_scope_id',
            'scope_value' => $category->id,
            'scope_url' => null,
        ];
    }

    /**
     * Category pages reuse the homepage section engine so every section type —
     * including the ones an admin adds later — renders identically here. Only
     * the section-owned content is read: a category page never auto-fills a
     * section from the shared homepage banners or testimonials.
     */
    protected function resolveSection(Category $category, CategoryPageSection $section): array
    {
        return app(HomePageService::class)->resolveSection($section, [
            'allowSharedFallbacks' => false,
            'product_ids' => $this->descendantIds($category),
        ]);
    }

    protected function applyCategoryScope(Builder $query, Category $category, ?array $ids = null): void
    {
        $ids ??= $this->descendantIds($category);

        $query->whereIn('category_id', $ids)
            ->orWhereIn('subcategory_id', $ids);
    }

    /** @return array<int, int> */
    protected function descendantIds(Category $category, array &$ids = []): array
    {
        $ids[] = $category->id;

        foreach ($category->children()->where('is_active', true)->get() as $child) {
            $this->descendantIds($child, $ids);
        }

        return array_values(array_unique($ids));
    }
}
