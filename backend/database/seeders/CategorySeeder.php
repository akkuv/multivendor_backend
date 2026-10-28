<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Electronics' => ['Mobile Accessories', 'Laptops', 'Audio & Video'],
            'Fashion' => ['Men', 'Women', 'Kids'],
            'Home & Kitchen' => ['Kitchen Appliances', 'Furniture', 'Decor'],
            'Beauty & Personal Care' => ['Skincare', 'Haircare', 'Makeup'],
            'Books' => ['Fiction', 'Education', 'Self Help'],
            'Sports & Outdoors' => ['Fitness', 'Cycling', 'Camping'],
            'Toys & Games' => ['Board Games', 'Action Figures', 'Puzzles'],
            'Grocery' => ['Staples', 'Dairy', 'Snacks'],
        ];

        foreach ($categories as $name => $subcategories) {
            $parent = Category::query()->firstOrCreate(
                ['name' => $name],
                [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'description' => "{$name} category",
                    'is_active' => true,
                    'sort_order' => 0,
                ],
            );

            foreach ($subcategories as $subcategoryName) {
                $subcategory = Category::query()->firstOrCreate(
                    ['parent_id' => $parent->id, 'name' => $subcategoryName],
                    [
                        'slug' => Str::slug($subcategoryName),
                        'description' => "{$subcategoryName} category",
                        'is_active' => true,
                        'sort_order' => 0,
                    ],
                );

                Category::factory()->count(3)->child($subcategory->id)->create();
            }
        }

        $this->assignCategoryImages();
    }

    /**
     * Attach an image to each top-level category so the homepage
     * "Shop by Category" row renders visually.
     */
    protected function assignCategoryImages(): void
    {
        $images = [
            'https://images.unsplash.com/photo-1498049794561-7780e7231661?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1445205170230-053b83016050?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1512820790803-83ca734da794?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1517649763962-0c623066013b?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1542838132-92c53300491e?w=400&h=400&fit=crop',
        ];

        $parents = Category::query()->whereNull('parent_id')->orderBy('id')->get();

        foreach ($parents->values() as $index => $category) {
            $category->update(['image' => $images[$index % count($images)]]);
        }
    }
}
