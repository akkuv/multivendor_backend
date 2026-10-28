<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject requests from users whose account has been deactivated (blocked).
 *
 * Login already refuses inactive accounts; this middleware makes a block take
 * effect immediately for a user who was already signed in. The token that
 * authenticated the request is invalidated and a 401 is returned so the
 * panels' API client clears its session and redirects to the login screen.
 */
class EnsureUserIsActive
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            if ($user->currentAccessToken() !== null) {
                $user->currentAccessToken()->delete();
            }

            return $this->error(__('Your account has been deactivated.'), null, 401);
        }

        return $next($request);
    }
}
