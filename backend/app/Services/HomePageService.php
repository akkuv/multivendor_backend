<?php

namespace App\Services;

use App\Enums\HomepageSectionType;
use App\Http\Resources\V1\ProductResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\CategoryPageSection;
use App\Models\CustomPage;
use App\Models\HomepageSection;
use App\Models\Product;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Builder;

class HomePageService
{
    /**
     * Whether an unconfigured section may fall back to a shared collection (global
     * banners, global testimonials, or an automatic category/product query). Only
     * the homepage does this; a custom page renders exactly what the admin set up,
     * so a freshly added section starts empty instead of showing default data.
     */
    protected bool $allowSharedFallbacks = true;

    /**
     * Category ids a product section is scoped to while resolving a category
     * page section. Null for the homepage and for custom pages, where the
     * section's own configured scope is used instead.
     *
     * @var array<int, int>|null
     */
    protected ?array $scopedCategoryIds = null;

    /**
     * Build the ordered section payload for the storefront homepage (no page) or
     * for one admin-created custom page. Both share the exact same section engine,
     * so every section type behaves identically wherever it is placed.
     */
    public function build(?CustomPage $page = null): array
    {
        $this->allowSharedFallbacks = $page === null;

        $sections = HomepageSection::query()
            ->when(
                $page,
                fn (Builder $query) => $query->where('custom_page_id', $page->id),
                fn (Builder $query) => $query->whereNull('custom_page_id'),
            )
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return [
            'sections' => $sections
                ->map(fn (HomepageSection $section) => $this->resolveSection($section))
                ->values()
                ->all(),
        ];
    }

    /**
     * The editor-owned items stored on a section's config, if any. An empty list
     * means the section falls back to the shared global collection.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function configItems(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];

        if (! array_key_exists('items', $config) || ! is_array($config['items'])) {
            return [];
        }

        return array_values(array_filter($config['items'], 'is_array'));
    }

    /**
     * Resolve one stored section into its storefront payload.
     *
     * Shared with {@see CategoryPageService} so every section type behaves
     * identically wherever it is placed. Callers may override the shared
     * fallback behaviour for the duration of the call and/or scope product
     * sections to a set of category ids (a category page passes its subtree).
     *
     * @param  array{allowSharedFallbacks?: bool, product_ids?: array<int, int>}  $context
     * @return array<string, mixed>
     */
    public function resolveSection(HomepageSection|CategoryPageSection $section, array $context = []): array
    {
        $previousFallbacks = $this->allowSharedFallbacks;
        $this->allowSharedFallbacks = $context['allowSharedFallbacks'] ?? true;
        $this->scopedCategoryIds = $context['product_ids'] ?? null;

        try {
            $type = $section->type;
            $content = match ($type) {
                HomepageSectionType::Hero => $this->resolveHero($section),
                HomepageSectionType::Categories => $this->resolveCategories($section),
                HomepageSectionType::Testimonials => $this->resolveTestimonials($section),
                HomepageSectionType::ValueProps => $this->resolveValueProps($section),
                HomepageSectionType::Personalization => $this->resolvePersonalization($section),
                HomepageSectionType::MiniBanners => $this->resolveMiniBanners($section),
                HomepageSectionType::FeaturedCategories => $this->resolveFeaturedCategories($section),
                HomepageSectionType::EditorsPicks => $this->resolveEditorsPicks($section),
                HomepageSectionType::SplitCollage => $this->resolveSplitCollage($section),
                HomepageSectionType::ImageBanner => $this->resolveImageBanner($section),
                HomepageSectionType::VideoBanner => $this->resolveVideoBanner($section),
                HomepageSectionType::ProductCarousel => $this->resolveProductCarousel($section),
                HomepageSectionType::TextBanner => $this->resolveTextBanner($section),
                HomepageSectionType::InstagramDiaries => $this->resolveInstagramDiaries($section),
                HomepageSectionType::TrendingStyles => $this->resolveTrendingStyles($section),
                HomepageSectionType::IconInfo => $this->resolveIconInfo($section),
                HomepageSectionType::VisitStores => $this->resolveVisitStores($section),
                HomepageSectionType::StoreLocations => $this->resolveStoreLocations($section),
                HomepageSectionType::RichText => $this->resolveRichText($section),
                HomepageSectionType::Faq => $this->resolveFaq($section),
                default => $this->resolveProducts($section, $type),
            };

            return [
                'id' => $section->id,
                'type' => $type->value,
                'title' => $section->title,
                'subtitle' => $section->subtitle,
                'config' => $section->config ?? [],
                'sort_order' => $section->sort_order,
                'content' => $content,
            ];
        } finally {
            $this->allowSharedFallbacks = $previousFallbacks;
            $this->scopedCategoryIds = null;
        }
    }

    protected function resolveHero(HomepageSection|CategoryPageSection $section): array
    {
        $items = $this->configItems($section);

        // A section that owns its own slides (the inline Hero editor) renders
        // exactly those, so every page keeps its own banner images and copy.
        if ($items !== []) {
            return collect($items)
                ->map(fn (array $item, int $index) => [
                    'id' => $index + 1,
                    'title' => $item['title'] ?? null,
                    'subtitle' => $item['subtitle'] ?? null,
                    'image' => $item['image'] ?? null,
                    'mobile_image' => $item['mobile_image'] ?? null,
                    'button_text' => $item['button_text'] ?? null,
                    'url' => $item['url'] ?? null,
                    'start_date' => null,
                    'end_date' => null,
                    'position' => 'HERO',
                    'sort_order' => $index,
                    'is_active' => true,
                ])
                ->values()
                ->all();
        }

        if (! $this->allowSharedFallbacks) {
            return [];
        }

        $today = now()->toDateString();

        return Banner::query()
            ->where('is_active', true)
            ->where('position', 'HERO')
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $today))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values()
            ->all();
    }

    protected function resolveCategories(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];
        $limit = (int) ($config['limit'] ?? 12);

        // Editor-owned tiles: once the admin has built the cards by hand, render
        // exactly those — uploaded image, title and description — and nothing else.
        if (array_key_exists('items', $config) && is_array($config['items'])) {
            return $this->resolveCategoryTiles($config['items']);
        }

        $categoryIds = $config['category_ids'] ?? [];

        // When the admin hand-picks categories, render exactly those — at any depth —
        // in the order they were chosen. Forcing top-level only here silently dropped
        // every selected subcategory, leaving the section with a single card.
        if (is_array($categoryIds) && $categoryIds !== []) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));

            $tiles = Category::query()
                ->withCount(['products' => fn ($q) => $q->live()])
                ->where('is_active', true)
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            return collect($ids)
                ->map(fn (int $id) => $tiles->get($id))
                ->filter()
                ->take($limit)
                ->map(fn (Category $category) => $this->categoryCard($category))
                ->values()
                ->all();
        }

        // No editor-configured tiles: render nothing rather than inventing a
        // default list of top-level categories. The section shows exactly what
        // the admin set up (and stays empty until they do).
        return [];
    }

    /**
     * Normalise a category into the flat card shape the "category ring" renders.
     *
     * @return array<string, mixed>
     */
    protected function categoryCard(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'image' => $category->image,
            'title' => null,
            'description' => null,
            'url' => null,
            'products_count' => $category->products_count,
        ];
    }

    /**
     * Build the editor-owned tiles for the Categories section. A tile is kept when
     * it carries an uploaded image or a title; its category (optional) supplies the
     * default link and item count.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function resolveCategoryTiles(array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            $id = (int) (is_array($item) ? ($item['category_id'] ?? 0) : 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $categories = Category::query()
            ->withCount(['products' => fn ($q) => $q->live()])
            ->where('is_active', true)
            ->whereIn('id', array_values(array_unique($ids)))
            ->get()
            ->keyBy('id');

        $cards = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $image = $this->trimmedOrNull($item['image'] ?? null);
            $title = $this->trimmedOrNull($item['title'] ?? null);
            $description = $this->trimmedOrNull($item['description'] ?? null);
            $url = $this->trimmedOrNull($item['url'] ?? null);

            // A tile is worth rendering when it has art or a heading.
            if ($image === null && $title === null) {
                continue;
            }

            $category = $categories->get((int) ($item['category_id'] ?? 0));

            $cards[] = [
                'id' => $category?->id,
                'name' => $title ?? (string) ($category?->name ?? ''),
                'slug' => $category?->slug,
                'image' => $image,
                'title' => $title,
                'description' => $description,
                'url' => $url ?? ($category?->slug ? '/category/'.$category->slug : null),
                'products_count' => $category?->products_count,
            ];
        }

        return $cards;
    }

    /** Trim a string value, collapsing blanks to null. */
    protected function trimmedOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    protected function resolveTestimonials(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];
        $items = $this->configItems($section);

        // Section-owned testimonials let each page show its own reviews instead
        // of the shared homepage list.
        if ($items !== []) {
            return collect($items)
                ->map(fn (array $item, int $index) => [
                    'id' => $index + 1,
                    'author_name' => (string) ($item['author_name'] ?? ''),
                    'author_role' => $item['author_role'] ?? null,
                    'author_avatar' => $item['author_avatar'] ?? null,
                    'image' => $item['image'] ?? null,
                    'url' => $item['url'] ?? null,
                    'content' => (string) ($item['content'] ?? ''),
                    'rating' => (int) ($item['rating'] ?? 5),
                    'sort_order' => $index,
                    'is_active' => true,
                ])
                ->values()
                ->all();
        }

        if (! $this->allowSharedFallbacks) {
            return [];
        }

        $limit = (int) ($config['limit'] ?? 6);

        return Testimonial::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->values()
            ->all();
    }

    /**
     * KALKI's thin personalisation strip: a single line of copy (the section
     * title, with the subtitle as an optional second line) beside one
     * admin-configured link.
     *
     * @return array<string, mixed>
     */
    protected function resolvePersonalization(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];

        return [
            'button_text' => (string) ($config['button_text'] ?? ''),
            'button_url' => (string) ($config['button_url'] ?? ''),
        ];
    }

    protected function resolveValueProps(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];

        $items = array_map(
            fn (array $item): array => [
                'icon' => (string) ($item['icon'] ?? 'gem'),
                'title' => (string) ($item['title'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
            ],
            is_array($config['items'] ?? null) ? $config['items'] : [],
        );

        return ['items' => $items];
    }

    /**
     * KALKI "The Saree Edit" style split feature: a media collage (one tall
     * video/poster tile + a wide image + two small images) beside a text block.
     *
     * @return array<string, mixed>
     */
    protected function resolveSplitCollage(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];

        return [
            'eyebrow' => (string) ($config['eyebrow'] ?? ''),
            'heading' => (string) ($config['heading'] ?? ''),
            'description' => (string) ($config['description'] ?? ''),
            'video' => (string) ($config['video'] ?? ''),
            'poster' => (string) ($config['poster'] ?? ''),
            'images' => array_values(array_filter(array_map(
                'strval',
                is_array($config['images'] ?? null) ? $config['images'] : [],
            ))),
            'button_text' => (string) ($config['button_text'] ?? ''),
            'button_url' => (string) ($config['button_url'] ?? ''),
            'media_position' => ($config['media_position'] ?? 'left') === 'right' ? 'right' : 'left',
        ];
    }

    /**
     * KALKI "Bride And Groom Collection" style band (now a generic image banner):
     * a centred section title above a single full-width banner image that links
     * to a collection.
     *
     * @return array<string, mixed>
     */
    protected function resolveImageBanner(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];

        return [
            'image' => (string) ($config['image'] ?? ''),
            'image_mobile' => (string) ($config['image_mobile'] ?? ''),
            'alt' => (string) ($config['alt'] ?? ''),
            'link_url' => (string) ($config['link_url'] ?? ''),
        ];
    }

    /**
     * KALKI "bridal wedding dresses" top banner (`video_OR_image_new`): a full-width
     * autoplaying video with a bottom-anchored white headline / description and a
     * single black CTA button overlaid on top of it. An optional portrait video is
     * swapped in below 750px, exactly like KALKI's separate mobile clip.
     *
     * @return array<string, mixed>
     */
    protected function resolveVideoBanner(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];

        return [
            'video' => (string) ($config['video'] ?? ''),
            'video_mobile' => (string) ($config['video_mobile'] ?? ''),
            'poster' => (string) ($config['poster'] ?? ''),
            'description' => (string) ($config['description'] ?? ''),
            'button_text' => (string) ($config['button_text'] ?? ''),
            'button_url' => (string) ($config['button_url'] ?? ''),
        ];
    }

    /**
     * KALKI "HANDCRAFTED WITH LOVE" (`List_collections_template_new`): a cream box
     * with a centred text column (heading + subtitle from the section columns, plus
     * a black "Shop Now" button) beside an autoplaying carousel of portrait 5:7
     * product cards. Each card carries the admin-managed image, name, price and link.
     *
     * @return array<string, mixed>
     */
    protected function resolveProductCarousel(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];

        $items = array_map(
            fn (array $item): array => [
                'image' => (string) ($item['image'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'price' => (string) ($item['price'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
            ],
            $this->configItems($section)
        );

        return [
            'button_text' => (string) ($config['button_text'] ?? ''),
            'button_url' => (string) ($config['button_url'] ?? ''),
            'items' => $items,
        ];
    }

    /**
     * KALKI "Shop With A Stylist" band (`index_form_app_detail_new`): an optional
     * full-bleed background image with centred text (the section title/subtitle come
     * from the section columns) over a single call-to-action button.
     *
     * @return array<string, mixed>
     */
    protected function resolveTextBanner(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];

        return [
            'image' => (string) ($config['image'] ?? ''),
            'alt' => (string) ($config['alt'] ?? ''),
            'description' => (string) ($config['description'] ?? ''),
            'button_text' => (string) ($config['button_text'] ?? ''),
            'button_url' => (string) ($config['button_url'] ?? ''),
        ];
    }

    /**
     * KALKI "Visit Our Instagram Diaries" feed: a centred title + handle above a
     * grid of image/video tiles. Tapping a tile opens a lightbox dialog with the
     * post media, caption and an optional permalink.
     *
     * @return array<string, mixed>
     */
    protected function resolveInstagramDiaries(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];
        $rawItems = is_array($config['items'] ?? null) ? $config['items'] : [];

        $items = array_map(
            fn (array $item): array => [
                'image' => (string) ($item['image'] ?? ''),
                'video' => (string) ($item['video'] ?? ''),
                'poster' => (string) ($item['poster'] ?? ''),
                'caption' => (string) ($item['caption'] ?? ''),
                'permalink' => (string) ($item['permalink'] ?? ''),
                'likes' => (string) ($item['likes'] ?? ''),
                'comments' => (string) ($item['comments'] ?? ''),
            ],
            array_filter(
                $rawItems,
                fn ($item): bool => is_array($item) && (($item['image'] ?? '') !== '' || ($item['video'] ?? '') !== ''),
            ),
        );

        return [
            'heading' => (string) ($config['heading'] ?? ''),
            'handle' => (string) ($config['handle'] ?? ''),
            'avatar' => (string) ($config['avatar'] ?? ''),
            'items' => array_values($items),
            'button_text' => (string) ($config['button_text'] ?? ''),
            'button_url' => (string) ($config['button_url'] ?? ''),
        ];
    }

    /**
     * KALKI "Trending Styles" slider: a centred title above a horizontal rail of
     * tall image/video cards, each with a product name and a "View" link.
     *
     * @return array<string, mixed>
     */
    protected function resolveTrendingStyles(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];
        $rawItems = is_array($config['items'] ?? null) ? $config['items'] : [];

        $items = array_map(
            fn (array $item): array => [
                'image' => (string) ($item['image'] ?? ''),
                'video' => (string) ($item['video'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
            ],
            array_filter(
                $rawItems,
                fn ($item): bool => is_array($item) && (($item['image'] ?? '') !== '' || ($item['video'] ?? '') !== ''),
            ),
        );

        return [
            'heading' => (string) ($config['heading'] ?? ''),
            'items' => array_values($items),
        ];
    }

    protected function resolveMiniBanners(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];
        $items = $config['items'] ?? null;
        $limit = (int) ($config['limit'] ?? 4);

        if (is_array($items) && $items !== []) {
            $result = array_map(function (array $item): array {
                return [
                    'id' => isset($item['id']) ? (int) $item['id'] : null,
                    'title' => (string) ($item['title'] ?? ''),
                    'subtitle' => (string) ($item['subtitle'] ?? ''),
                    'description' => (string) ($item['description'] ?? ''),
                    'image' => (string) ($item['image'] ?? ''),
                    'button_text' => (string) ($item['button_text'] ?? ''),
                    'url' => (string) ($item['url'] ?? ''),
                ];
            }, $items);

            return array_values(array_filter($result, fn ($i) => $i['image'] !== '' || $i['title'] !== ''));
        }

        $categoryIds = $config['category_ids'] ?? [];

        if (is_array($categoryIds) && $categoryIds !== []) {
            $cats = Category::query()
                ->where('is_active', true)
                ->whereIn('id', $categoryIds)
                ->orderByRaw('FIELD(id, '.implode(',', array_map('intval', $categoryIds)).')')
                ->limit($limit)
                ->get();

            return $cats->map(function ($c) {
                return [
                    'id' => $c->id,
                    'title' => $c->name,
                    'subtitle' => $c->subtitle ?? '',
                    'description' => $c->description ?? '',
                    'image' => $c->image ?? '',
                    'button_text' => 'Shop now',
                    'url' => $c->slug ? '/category/'.$c->slug : '/shop?category_id='.$c->id,
                ];
            })->values()->all();
        }

        if (! $this->allowSharedFallbacks) {
            return [];
        }

        $imageCategories = Category::query()
            ->where('is_active', true)
            ->whereNotNull('image')
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();

        if ($imageCategories->count() < $limit) {
            $others = Category::query()
                ->where('is_active', true)
                ->whereNull('image')
                ->orderBy('sort_order')
                ->limit($limit - $imageCategories->count())
                ->get();

            $imageCategories = $imageCategories->concat($others);
        }

        return $imageCategories->map(function ($c) {
            return [
                'id' => $c->id,
                'title' => $c->name,
                'subtitle' => $c->subtitle ?? '',
                'description' => $c->description ?? '',
                'image' => $c->image ?? '',
                'button_text' => 'Shop now',
                'url' => $c->slug ? '/category/'.$c->slug : '/shop?category_id='.$c->id,
            ];
        })->values()->all();
    }

    protected function resolveFeaturedCategories(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];
        $items = $config['items'] ?? null;

        // These cards are entirely editor-owned: only cards the admin created are
        // rendered, and each one must have its own uploaded image. A category's own
        // image is never used as a stand-in, so the section stays free of default art.
        // The category itself is optional — a card that is just art (optionally with
        // its own title, caption and link) still renders.
        if (! is_array($items) || $items === []) {
            return [];
        }

        $ids = [];
        foreach ($items as $item) {
            $id = (int) ($item['category_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $categories = Category::query()
            ->where('is_active', true)
            ->whereIn('id', array_values(array_unique($ids)))
            ->get()
            ->keyBy('id');

        $cards = [];
        foreach ($items as $item) {
            $image = $item['image'] ?? null;

            if (! is_string($image) || $image === '') {
                continue;
            }

            $category = $categories->get((int) ($item['category_id'] ?? 0));
            $title = $item['title'] ?? null;

            $cards[] = [
                'id' => $category?->id,
                'name' => $category?->name ?? (is_string($title) ? $title : ''),
                'slug' => $category?->slug,
                'parent_id' => $category?->parent_id,
                'image' => $image,
                'title' => $title,
                'subtitle' => $item['subtitle'] ?? null,
                'url' => $item['url'] ?? null,
            ];
        }

        return $cards;
    }

    protected function resolveEditorsPicks(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];
        $items = $config['items'] ?? null;
        $categoryIds = $config['category_ids'] ?? [];
        $limit = (int) ($config['limit'] ?? 2);

        // Editor-configured banner cards (image upload + link) take precedence.
        if (is_array($items) && $items !== []) {
            return $this->normalizeBannerItems($items);
        }

        if (is_array($categoryIds) && $categoryIds !== []) {
            return Category::query()
                ->where('is_active', true)
                ->whereIn('id', $categoryIds)
                ->orderByRaw('FIELD(id, '.implode(',', array_map('intval', $categoryIds)).')')
                ->limit($limit)
                ->get()
                ->map(fn (Category $category): array => $this->categoryToBannerItem($category))
                ->values()
                ->all();
        }

        if (! $this->allowSharedFallbacks) {
            return [];
        }

        $imageCategories = Category::query()
            ->where('is_active', true)
            ->whereNotNull('image')
            ->orderBy('sort_order')
            ->limit($limit + 4)
            ->get();

        if ($imageCategories->count() < $limit) {
            $others = Category::query()
                ->where('is_active', true)
                ->whereNull('image')
                ->orderBy('sort_order')
                ->limit($limit - $imageCategories->count())
                ->get();

            $imageCategories = $imageCategories->concat($others);
        }

        return $imageCategories->take($limit)
            ->map(fn (Category $category): array => $this->categoryToBannerItem($category))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeBannerItems(array $items): array
    {
        $normalized = array_map(fn (array $item): array => [
            'id' => isset($item['id']) ? (int) $item['id'] : null,
            'title' => (string) ($item['title'] ?? ''),
            'subtitle' => (string) ($item['subtitle'] ?? ''),
            'description' => (string) ($item['description'] ?? ''),
            'image' => (string) ($item['image'] ?? ''),
            'button_text' => (string) ($item['button_text'] ?? ''),
            'url' => (string) ($item['url'] ?? ''),
        ], $items);

        return array_values(array_filter(
            $normalized,
            fn (array $item): bool => $item['image'] !== '' || $item['title'] !== '',
        ));
    }

    /** @return array<string, mixed> */
    protected function categoryToBannerItem(Category $category): array
    {
        return [
            'id' => $category->id,
            'title' => $category->name,
            'subtitle' => $category->subtitle ?? '',
            'description' => $category->description ?? '',
            'image' => $category->image ?? '',
            'button_text' => 'Shop now',
            'url' => $category->slug ? '/category/'.$category->slug : '/shop?category_id='.$category->id,
        ];
    }

    protected function resolveIconInfo(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];

        $items = array_map(
            fn (array $item): array => [
                'icon' => (string) ($item['icon'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
            ],
            array_values(array_filter(
                is_array($config['items'] ?? null) ? $config['items'] : [],
                'is_array',
            )),
        );

        return ['items' => $items];
    }

    protected function resolveVisitStores(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];

        return [
            'eyebrow' => (string) ($config['eyebrow'] ?? ''),
            'title' => (string) ($config['title'] ?? ''),
            'description' => (string) ($config['description'] ?? ''),
            'button_text' => (string) ($config['button_text'] ?? ''),
            'button_url' => (string) ($config['button_url'] ?? ''),
        ];
    }

    protected function resolveStoreLocations(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];

        $items = array_map(
            fn (array $item): array => [
                'image' => (string) ($item['image'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'button_text' => (string) ($item['button_text'] ?? ''),
                'button_url' => (string) ($item['button_url'] ?? ''),
                'secondary_button_text' => (string) ($item['secondary_button_text'] ?? ''),
                'secondary_button_url' => (string) ($item['secondary_button_url'] ?? ''),
            ],
            array_values(array_filter(
                is_array($config['items'] ?? null) ? $config['items'] : [],
                'is_array',
            )),
        );

        return [
            'heading' => (string) ($config['heading'] ?? ''),
            'items' => $items,
        ];
    }

    protected function resolveRichText(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];
        $html = is_string($config['html'] ?? null) ? trim($config['html']) : '';

        return ['html' => $html];
    }

    protected function resolveFaq(HomepageSection|CategoryPageSection $section): array
    {
        $config = $section->config ?? [];

        $items = array_map(
            fn (array $item): array => [
                'question' => (string) ($item['question'] ?? ''),
                'answer' => (string) ($item['answer'] ?? ''),
            ],
            array_values(array_filter(
                is_array($config['items'] ?? null) ? $config['items'] : [],
                'is_array',
            )),
        );

        $items = array_values(array_filter(
            $items,
            fn (array $item): bool => $item['question'] !== '' && $item['answer'] !== '',
        ));

        return ['items' => $items];
    }

    protected function resolveProducts(HomepageSection|CategoryPageSection $section, HomepageSectionType $type): array
    {
        $config = $section->config ?? [];
        $limit = (int) ($config['limit'] ?? 10);
        $productIds = $config['product_ids'] ?? [];
        $hasExplicitProductIds = is_array($productIds) && $productIds !== [];
        $categoryId = $config['category_id'] ?? null;

        // A products section shows only what the admin picked — a custom page
        // never auto-fills with default (latest / best-selling) products.
        if (! $hasExplicitProductIds && ! $categoryId && ! $this->allowSharedFallbacks && $this->scopedCategoryIds === null) {
            return [];
        }

        $query = $this->baseProductQuery();

        if ($this->scopedCategoryIds !== null) {
            // Category page: the section is scoped to the category subtree
            // unless the admin explicitly pinned products or another category.
            if (! $hasExplicitProductIds && ! $categoryId) {
                $query->where(fn (Builder $scoped) => $scoped
                    ->whereIn('category_id', $this->scopedCategoryIds)
                    ->orWhereIn('subcategory_id', $this->scopedCategoryIds));
            }
        }

        if ($categoryId) {
            $query->where('category_id', (int) $categoryId);
        }

        if ($hasExplicitProductIds) {
            $products = (clone $query)
                ->whereIn('id', $productIds)
                ->get()
                ->sortBy(fn ($p) => array_search($p->id, $productIds))
                ->values();
        } else {
            $products = $this->applyTypeFilter(clone $query, $type)->limit($limit)->get();
        }

        return ProductResource::collection($products->take($hasExplicitProductIds ? count($productIds) : $limit))->resolve();
    }

    protected function baseProductQuery(): Builder
    {
        return Product::query()
            ->with(['vendor', 'category', 'brand', 'images'])
            ->withAvg(['reviews as rating_avg' => fn (Builder $q) => $q->where('is_approved', true)], 'rating')
            ->withCount(['reviews as rating_count' => fn (Builder $q) => $q->where('is_approved', true)])
            ->live();
    }

    protected function applyTypeFilter(Builder $query, HomepageSectionType $type): Builder
    {
        return match ($type) {
            HomepageSectionType::BestSellers => $query->where('best_seller', true)->orderByDesc('rating_count')->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };
    }
}
