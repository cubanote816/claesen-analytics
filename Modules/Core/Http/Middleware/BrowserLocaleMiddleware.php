<?php

namespace Modules\Core\Http\Middleware;

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
        // Only use 'nl' if it's the specifically preferred language, otherwise default to 'en'
        $preferredLocale = $request->getPreferredLanguage(['nl', 'en']);
        $locale = ($preferredLocale === 'nl') ? 'nl' : 'en';

        App::setLocale($locale);
        $request->session()->put('locale', $locale);

        return $next($request);
    }
}
