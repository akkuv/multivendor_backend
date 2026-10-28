<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AcceptLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('X-Locale') ?? substr($request->header('Accept-Language', ''), 0, 2);
        if ($locale && in_array($locale, ['en', 'hi'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
