<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class BrowserLocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $preferredLocale = $request->getPreferredLanguage(['nl', 'en']);

        // If Dutch is preferred, use nl, otherwise default to en
        $locale = $preferredLocale === 'nl' ? 'nl' : 'en';

        App::setLocale($locale);
        $request->session()->put('locale', $locale);

        return $next($request);
    }
}
