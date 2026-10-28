<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_settings', function (Blueprint $table) {
            $table->id();
            $table->json('navbar')->nullable();
            $table->json('footer')->nullable();
            $table->timestamps();
        });

        DB::table('website_settings')->insert([
            'navbar' => json_encode([
                'announcement' => [
                    'enabled' => true,
                    'text' => 'Free shipping on orders over ₹499',
                    'url' => null,
                ],
                'logo' => [
                    'text' => 'Waplia',
                    'image' => null,
                    'url' => '/',
                ],
                'links' => [
                    ['label' => 'Home', 'url' => '/', 'new_tab' => false],
                    ['label' => 'Shop', 'url' => '/shop', 'new_tab' => false],
                ],
                'departments' => [
                    'enabled' => true,
                    'category_ids' => null,
                    'show_all' => true,
                    'show_shop_all' => true,
                    'show_collections' => true,
                ],
            ]),
            'footer' => json_encode([
                'about' => [
                    'text' => 'Waplia is a multi-vendor marketplace connecting you with verified sellers across thousands of products.',
                ],
                'columns' => [
                    [
                        'title' => 'Shop',
                        'links' => [
                            ['label' => 'All Products', 'url' => '/shop', 'new_tab' => false],
                            ['label' => 'Deals', 'url' => '/shop?sort=discount', 'new_tab' => false],
                        ],
                    ],
                    [
                        'title' => 'My Account',
                        'links' => [
                            ['label' => 'My Account', 'url' => '/account', 'new_tab' => false],
                            ['label' => 'Wishlist', 'url' => '/wishlist', 'new_tab' => false],
                            ['label' => 'Cart', 'url' => '/cart', 'new_tab' => false],
                        ],
                    ],
                    [
                        'title' => 'Help',
                        'links' => [
                            ['label' => 'Track Order', 'url' => '/account', 'new_tab' => false],
                            ['label' => 'Returns', 'url' => '/account/returns', 'new_tab' => false],
                        ],
                    ],
                ],
                'social' => [
                    'facebook' => null,
                    'instagram' => null,
                    'twitter' => null,
                    'youtube' => null,
                    'linkedin' => null,
                    'whatsapp' => null,
                ],
                'contact' => [
                    'email' => 'support@waplia.com',
                    'phone' => '+91 00000 00000',
                    'address' => null,
                ],
                'copyright' => 'All rights reserved.',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('website_settings');
    }
};
