<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\CustomPage;
use App\Services\HomePageService;
use Illuminate\Http\JsonResponse;

class CustomPageController extends Controller
{
    public function __construct(
        private readonly HomePageService $homePageService,
    ) {}

    public function show(string $slug): JsonResponse
    {
        $page = CustomPage::query()
            ->where('slug', trim($slug, '/'))
            ->where('is_active', true)
            ->first();

        if (! $page) {
            return $this->error(__('Page not found.'), null, 404);
        }

        return $this->success(__('Page fetched successfully.'), [
            'page' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
            ],
            'sections' => $this->homePageService->build($page)['sections'],
        ]);
    }
}
