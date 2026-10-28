<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\User;
use App\Models\VendorOrder;
use App\Observers\OrderObserver;
use App\Observers\VendorOrderObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Order::observe(OrderObserver::class);
        VendorOrder::observe(VendorOrderObserver::class);

        // Super admins operate through their own role-protected endpoints, but
        // a blanket pass keeps every policy from having to special-case them
        // (returning null lets the policies decide for every other role).
        Gate::before(fn (?User $user): ?bool => $user?->isSuperAdmin() ? true : null);

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                Str::lower($request->string('email')).'|'.$request->ip()
            );
        });

        RateLimiter::for('forgot-password', function (Request $request) {
            return Limit::perMinute(3)->by(
                Str::lower($request->string('email')).'|'.$request->ip()
            );
        });

        RateLimiter::for('verification', function (Request $request) {
            return Limit::perMinute(3)->by(
                $request->user()?->id ?? $request->ip()
            );
        });
    }
}
