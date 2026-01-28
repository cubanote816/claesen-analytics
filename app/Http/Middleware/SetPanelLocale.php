<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\App;

class SetPanelLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $acceptLanguage = $request->header('Accept-Language');

        // Check if English is explicitly requested (en, en-US, en-GB, etc.)
        if ($acceptLanguage && str_starts_with(strtolower($acceptLanguage), 'en')) {
            App::setLocale('en');
        } else {
            // Default to Dutch for everything else (nl, es, fr, etc.)
            App::setLocale('nl');
        }

        return $next($request);
    }
}
