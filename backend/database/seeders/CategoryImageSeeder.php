<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategoryImageSeeder extends Seeder
{
    /**
     * Real photos for every subcategory so the storefront's
     * "Browse Subcategories" grid renders visually rich tiles.
     *
     * @var array<string, array<string, string>> parent slug => child name => image
     */
    protected const IMAGES = [
        'electronics' => [
            'Mobile Accessories' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9',
            'Laptops' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853',
            'Audio & Video' => 'https://images.unsplash.com/photo-1484704849700-f032a568e944',
        ],
        'fashion' => [
            'Men' => 'https://images.unsplash.com/photo-1490578474895-699cd4e2cf59',
            'Women' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b',
            'Kids' => 'https://images.unsplash.com/photo-1503919545889-aef636e10ad4',
        ],
        'home-kitchen' => [
            'Kitchen Appliances' => 'https://images.unsplash.com/photo-1556911220-bff31c812dba',
            'Furniture' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc',
            'Decor' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7',
        ],
        'beauty-personal-care' => [
            'Skincare' => 'https://images.unsplash.com/photo-1556228578-8c89e6adf883',
            'Haircare' => 'https://images.unsplash.com/photo-1522337660859-02fbefca4702',
            'Makeup' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348',
        ],
        'books' => [
            'Fiction' => 'https://images.unsplash.com/photo-1512820800793-83ca734da794',
            'Education' => 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b',
            'Self Help' => 'https://images.unsplash.com/photo-1513475382585-d06e58bcb0e0',
        ],
        'sports-outdoors' => [
            'Fitness' => 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b',
            'Cycling' => 'https://images.unsplash.com/photo-1532298229144-0ec0c57515c7',
            'Camping' => 'https://images.unsplash.com/photo-1504280390367-361c6d9f38f4',
        ],
        'toys-games' => [
            'Board Games' => 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af',
            'Action Figures' => 'https://images.unsplash.com/photo-1558060370-d644479cb6f7',
            'Puzzles' => 'https://images.unsplash.com/photo-1612036782180-6f0b6cd846fe',
        ],
        'grocery' => [
            'Staples' => 'https://images.unsplash.com/photo-1585032226651-759b368d7246',
            'Dairy' => 'https://images.unsplash.com/photo-1550583724-b2692b85b150',
            'Snacks' => 'https://images.unsplash.com/photo-1518977676601-b53f82aba655',
        ],
        'womens-clothing' => [
            'Dresses' => 'https://images.unsplash.com/photo-1595777457583-95e059d581b8',
            'Tops & Blouses' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab',
            'Jeans & Trousers' => 'https://images.unsplash.com/photo-1542272604-787c3835535d',
            'Outerwear' => 'https://images.unsplash.com/photo-1544022613-e87ca75a784a',
            'Skirts & Shorts' => 'https://images.unsplash.com/photo-1583496661160-fb5886a0aaaa',
        ],
        'mens-clothing' => [
            'T-Shirts' => 'https://images.unsplash.com/photo-1576566588028-4147f3842f27',
            'Shirts' => 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c',
            'Jeans & Trousers' => 'https://images.unsplash.com/photo-1542272604-787c3835535d',
            'Hoodies & Jackets' => 'https://images.unsplash.com/photo-1551028719-00167b16eac5',
            'Formal Wear' => 'https://images.unsplash.com/photo-1594938298603-c8148c4dae35',
        ],
        'kids-clothing' => [
            'Boys' => 'https://images.unsplash.com/photo-1584362917165-526a968579e8',
            'Girls' => 'https://images.unsplash.com/photo-1519238263530-99bdd11df2ea',
            'Babies' => 'https://images.unsplash.com/photo-1555252333-9f8e92e65df9',
        ],
    ];

    public function run(): void
    {
        foreach (self::IMAGES as $parentSlug => $children) {
            $parent = Category::query()->where('slug', $parentSlug)->whereNull('parent_id')->first();

            if ($parent === null) {
                continue;
            }

            foreach ($children as $childName => $image) {
                $parent->children()->where('name', $childName)->first()?->update([
                    'image' => "{$image}?w=600&h=450&fit=crop",
                ]);
            }
        }
    }
}
