<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\Testimonial;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;

class HomepageSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSections();
        $this->seedBanners();
        $this->seedTestimonials();
    }

    protected function seedSections(): void
    {
        // Featured Category cards need their own uploaded image — the section never
        // falls back to a category's image — so the demo ships square card art.
        $featuredCategoryCandidates = [
            "Men's Dhoti Kurta" => '/demo/instagram/ig-7.jpg',
            'Instant Saree' => '/demo/instagram/ig-1.jpg',
            'Wedding Lehenga' => '/demo/instagram/ig-2.jpg',
            'Bridal' => '/demo/instagram/ig-6.jpg',
            'Mehendi Wear' => '/demo/instagram/ig-4.jpg',
            'Pant Suit' => '/demo/instagram/ig-3.jpg',
            'Kids' => '/demo/instagram/ig-5.jpg',
            'Jewellery & Accessories' => '/demo/instagram/ig-8.jpg',
        ];

        $featuredCategoryItems = [];
        foreach ($featuredCategoryCandidates as $name => $image) {
            if (count($featuredCategoryItems) >= 7) {
                break;
            }

            $id = Category::query()->where('name', $name)->where('is_active', true)->value('id');
            if ($id) {
                $featuredCategoryItems[] = ['category_id' => $id, 'image' => $image];
            }
        }

        $storeLocationItems = [];
        foreach (
            VendorProfile::query()
                ->where('status', 'APPROVED')
                ->orderBy('id')
                ->limit(12)
                ->get(['id', 'store_name', 'logo']) as $vendor
        ) {
            $storeLocationItems[] = [
                'image' => (string) $vendor->logo,
                'name' => (string) $vendor->store_name,
                'button_text' => 'Visit Store',
                'button_url' => '/vendors/'.$vendor->id,
                'secondary_button_text' => null,
                'secondary_button_url' => null,
            ];
        }

        $sections = [
            ['type' => 'HERO', 'title' => null, 'subtitle' => null, 'sort_order' => 0],
            ['type' => 'PERSONALIZATION', 'title' => 'Curated pieces, styled to your taste', 'subtitle' => null, 'sort_order' => 1, 'config' => [
                'button_text' => 'View Your Edit',
                'button_url' => '/featured-products',
            ]],
            ['type' => 'VALUE_PROPS', 'title' => null, 'subtitle' => null, 'sort_order' => 2, 'config' => [
                'items' => [
                    ['icon' => 'gem', 'title' => 'Curated selection', 'description' => 'Every piece chosen by our editors for enduring quality and craft.'],
                    ['icon' => 'truck', 'title' => 'Lightning shipping', 'description' => 'Complimentary, fully insured delivery on every order over the threshold.'],
                    ['icon' => 'shield_check', 'title' => 'The 30-day promise', 'description' => 'Effortless returns and exchanges within thirty days of receiving your order.'],
                    ['icon' => 'sparkles', 'title' => 'Concierge support', 'description' => 'A dedicated concierge is on hand for styling advice and order care.'],
                ],
            ]],
            ['type' => 'MINI_BANNERS', 'title' => null, 'subtitle' => null, 'sort_order' => 3],
            ['type' => 'BEST_SELLERS', 'title' => 'Best Sellers', 'subtitle' => 'Most popular products', 'sort_order' => 4],
            ['type' => 'TRENDING_STYLES', 'title' => 'Trending Styles', 'subtitle' => null, 'sort_order' => 5, 'config' => [
                'heading' => 'Trending Styles',
                'items' => [
                    ['image' => '/demo/instagram/ig-1.jpg', 'video' => null, 'title' => 'Navy Layered Kanjivaram Saree', 'url' => '/product/navy-layered-kanjivaram-saree'],
                    ['image' => '/demo/instagram/ig-2.jpg', 'video' => '/demo/instagram/ig-video-1.mp4', 'title' => 'Maroon Engagement Lehenga Set', 'url' => '/product/maroon-engagement-lehenga-set'],
                    ['image' => '/demo/instagram/ig-3.jpg', 'video' => null, 'title' => 'Navy Mirror Work Party Gown', 'url' => '/product/navy-mirror-work-party-gown'],
                    ['image' => '/demo/instagram/ig-4.jpg', 'video' => '/demo/instagram/ig-video-2.mp4', 'title' => 'Wine Velvet Party Gown', 'url' => '/product/wine-velvet-party-gown'],
                    ['image' => '/demo/instagram/ig-5.jpg', 'video' => null, 'title' => 'Gota Patti Fusion Jumpsuit', 'url' => '/product/gota-patti-fusion-jumpsuit'],
                    ['image' => '/demo/instagram/ig-6.jpg', 'video' => null, 'title' => 'Ivory Zari Nehru Jacket', 'url' => '/product/ivory-zari-nehru-jacket'],
                    ['image' => '/demo/instagram/ig-7.jpg', 'video' => null, 'title' => 'Stone Dupatta', 'url' => '/product/stone-dupatta'],
                    ['image' => '/demo/instagram/ig-8.jpg', 'video' => null, 'title' => 'Silver Potli Bag', 'url' => '/product/silver-potli-bag'],
                ],
            ]],
            ['type' => 'FEATURED_CATEGORIES', 'title' => 'Featured Categories', 'subtitle' => 'Browse every department', 'sort_order' => 6, 'config' => [
                'items' => $featuredCategoryItems,
            ]],
            ['type' => 'TEXT_BANNER', 'title' => 'Shop With A Stylist', 'subtitle' => 'Expert advice, just a video call away.', 'sort_order' => 7, 'config' => [
                'image' => '/demo/text-banner/banner.jpg',
                'alt' => 'Shop With A Stylist',
                'description' => 'Our Stylists speak English, Hindi, Gujarati & Marathi',
                'button_text' => 'START CALL NOW',
                'button_url' => '/contact',
            ]],
            ['type' => 'EDITORS_PICKS', 'title' => 'Editor’s Picks', 'subtitle' => 'Two directions, one considered edit of the season.', 'sort_order' => 9],
            ['type' => 'SPLIT_COLLAGE', 'title' => 'The Saree Edit', 'subtitle' => null, 'sort_order' => 10, 'config' => [
                'eyebrow' => 'THE PERFECT DRAPE, MADE EASY',
                'heading' => 'INSTANT SAREE',
                'description' => 'The sarees you love, expertly pre-draped for you.',
                'poster' => 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=600&h=800&fit=crop',
                'images' => [
                    'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=800&h=520&fit=crop',
                    'https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=420&h=560&fit=crop',
                    'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=420&h=560&fit=crop',
                ],
                'button_text' => 'PRE-DRAPE NOW',
                'button_url' => '/shop',
                'media_position' => 'left',
            ]],
            ['type' => 'IMAGE_BANNER', 'title' => 'Bride And Groom Collection', 'subtitle' => null, 'sort_order' => 11, 'config' => [
                'image' => '/demo/bride-groom/banner-desktop.jpg',
                'image_mobile' => '/demo/bride-groom/banner-mobile.jpg',
                'alt' => 'Bride And Groom Collection',
                'link_url' => '/collections/bridal',
            ]],
            ['type' => 'ICON_INFO', 'title' => null, 'subtitle' => null, 'sort_order' => 15, 'config' => [
                'items' => [
                    ['icon' => 'video', 'title' => 'Book a video styling session', 'description' => 'Expert advice, just a video call away.', 'url' => '/contact'],
                    ['icon' => 'sparkles', 'title' => 'Sizing & styling help', 'description' => 'Size guides, fabric notes and order care.', 'url' => '/help'],
                ],
            ]],
            ['type' => 'INSTAGRAM_DIARIES', 'title' => 'Visit Our Instagram Diaries', 'subtitle' => null, 'sort_order' => 17, 'config' => [
                'heading' => 'VISIT OUR INSTAGRAM DIARIES',
                'handle' => 'Follow To Know More @waplia',
                'items' => [],
            ]],
            ['type' => 'VISIT_STORES', 'title' => 'Visit us or reach out', 'subtitle' => null, 'sort_order' => 18, 'config' => [
                'eyebrow' => 'Visit our stores',
                'title' => 'Visit us or reach out',
                'description' => 'Questions about orders, sizing or styling? Our team is a message away.',
                'button_text' => 'Contact us',
                'button_url' => '/contact',
            ]],
            ['type' => 'STORE_LOCATIONS', 'title' => 'Visit our Stores', 'subtitle' => null, 'sort_order' => 19, 'config' => [
                'heading' => 'Visit our Stores',
                'items' => $storeLocationItems,
            ]],
            ['type' => 'TESTIMONIALS', 'title' => 'What Our Customers Say', 'subtitle' => null, 'sort_order' => 20],
        ];

        foreach ($sections as $data) {
            HomepageSection::updateOrCreate(
                ['type' => $data['type']],
                array_merge($data, ['is_active' => true]),
            );
        }

        HomepageSection::query()->where('type', 'VENDOR_STORES')->delete();
    }

    protected function seedBanners(): void
    {
        $categoryUrl = function (string $name): string {
            $id = Category::query()->where('name', $name)->value('id');

            return $id ? "/shop?category_id={$id}" : '/shop';
        };

        $banners = [
            [
                'title' => 'Wedding Couture 2026',
                'subtitle' => 'Bridal lehengas, sarees & sherwanis for the big day',
                'image' => 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=1200&h=500&fit=crop',
                'mobile_image' => 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=600&h=800&fit=crop',
                'button_text' => 'Shop Bridal',
                'url' => $categoryUrl('Bridal'),
                'position' => 'HERO',
                'sort_order' => 0,
            ],
            [
                'title' => 'The Lehenga Edit',
                'subtitle' => 'Hand-embroidered silhouettes for every celebration',
                'image' => 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=1200&h=500&fit=crop',
                'mobile_image' => 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=600&h=800&fit=crop',
                'button_text' => 'Shop Lehenga',
                'url' => $categoryUrl('Lehenga'),
                'position' => 'HERO',
                'sort_order' => 1,
            ],
            [
                'title' => 'Sarees, Reimagined',
                'subtitle' => 'Kanjivaram, bandhani & sequin drapes',
                'image' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=1200&h=500&fit=crop',
                'mobile_image' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=600&h=800&fit=crop',
                'button_text' => 'Shop Saree',
                'url' => $categoryUrl('Saree'),
                'position' => 'HERO',
                'sort_order' => 2,
            ],
        ];

        foreach ($banners as $data) {
            Banner::updateOrCreate(
                ['title' => $data['title']],
                array_merge($data, ['is_active' => true]),
            );
        }

        Banner::query()->whereNotIn('title', array_column($banners, 'title'))->delete();
    }

    protected function seedTestimonials(): void
    {
        $testimonials = [
            [
                'author_name' => 'Ananya Iyer',
                'author_role' => 'Bride',
                'content' => 'My bridal lehenga was beyond perfect — the zardozi detailing was flawless and delivery was on time for the wedding.',
                'rating' => 5,
                'sort_order' => 0,
            ],
            [
                'author_name' => 'Ritika Malhotra',
                'author_role' => 'Bridesmaid',
                'content' => 'Ordered coordinated lehengas for my sister’s sangeet. The colours matched exactly and everyone loved them.',
                'rating' => 5,
                'sort_order' => 1,
            ],
            [
                'author_name' => 'Kavya Reddy',
                'author_role' => 'Festive Shopper',
                'content' => 'The Kanjivaram saree is gorgeous — rich silk, neat border and beautifully packaged. Will shop again.',
                'rating' => 4,
                'sort_order' => 2,
            ],
            [
                'author_name' => 'Aditya Nair',
                'author_role' => 'Groom',
                'content' => 'Ivory sherwani fit like couture. The styling concierge helped me pick the right dupatta and stole.',
                'rating' => 5,
                'sort_order' => 3,
            ],
            [
                'author_name' => 'Sneha Kapoor',
                'author_role' => 'Verified Buyer',
                'content' => 'Quick checkout and live tracking. My mother-of-the-bride saree arrived looking exactly like the photos.',
                'rating' => 5,
                'sort_order' => 4,
            ],
        ];

        foreach ($testimonials as $data) {
            Testimonial::updateOrCreate(
                ['author_name' => $data['author_name']],
                array_merge($data, ['is_active' => true]),
            );
        }

        Testimonial::query()->whereNotIn('author_name', array_column($testimonials, 'author_name'))->delete();
    }
}
