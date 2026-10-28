<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('X-Locale')
            ?? $request->get('lang')
            ?? $request->get('locale')
            ?? substr($request->header('Accept-Language', ''), 0, 2);

        if ($locale && in_array($locale, ['en', 'hi'], true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
