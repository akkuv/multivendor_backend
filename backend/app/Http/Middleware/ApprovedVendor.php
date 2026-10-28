<?php

namespace App\Http\Middleware;

use App\Enums\VendorStatus;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApprovedVendor
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->error(__('Unauthenticated. Please login.'), null, 401);
        }

        if (! $user->isVendor() || ! $user->vendorProfile) {
            return $this->error(__('Vendor profile not found.'), null, 403);
        }

        if ($user->vendorProfile->status !== VendorStatus::Approved->value) {
            return $this->error(__('Your vendor account is pending approval.'), null, 403);
        }

        return $next($request);
    }
}
