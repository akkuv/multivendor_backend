<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Services\HomePageService;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    public function __construct(
        protected HomePageService $homePageService,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success(__('Home page fetched successfully.'), $this->homePageService->build());
    }
}
