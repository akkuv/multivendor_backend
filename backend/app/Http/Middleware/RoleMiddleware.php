<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    use ApiResponse;

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->error(__('Unauthenticated. Please login.'), null, 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return $this->error(__('You are not authorized to access this resource.'), null, 403);
        }

        return $next($request);
    }
}
