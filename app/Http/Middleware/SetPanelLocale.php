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

        // Check if Dutch is requested (nl, nl-BE, nl-NL, etc.)
        if ($acceptLanguage && str_starts_with(strtolower($acceptLanguage), 'nl')) {
            App::setLocale('nl');
        } else {
            // Default to English for everything else (en, es, fr, etc.)
            App::setLocale('en');
        }

        return $next($request);
    }
}
