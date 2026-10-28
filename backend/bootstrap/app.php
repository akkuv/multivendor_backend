<?php

use App\Exceptions\Handler as ExceptionHandler;
use App\Http\Middleware\ApprovedVendor;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SetLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            Route::prefix('api/v1')
                ->middleware('api')
                ->group(base_path('routes/api_v1.php'));
        },
    )
    // Live support chat: `php artisan reverb:start` serves the websocket layer
    // and `/broadcasting/auth` authorises each private channel subscription.
    ->withBroadcasting(
        channels: __DIR__.'/../routes/channels.php',
        attributes: ['prefix' => '', 'middleware' => ['auth:sanctum']],
    )
    // Production: run `* * * * * cd <app> && php artisan schedule:run` in cron so scheduled tasks execute.
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('commission:settle')->dailyAt('02:00');
        $schedule->command('wishlist:check-notifications')->everyFifteenMinutes();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');

        $middleware->api(append: [
            SetLocale::class,
            ForceJsonResponse::class,
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'approved.vendor' => ApprovedVendor::class,
            'active' => EnsureUserIsActive::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return app(ExceptionHandler::class)->render($request, $e);
            }
        });
    })->create();
