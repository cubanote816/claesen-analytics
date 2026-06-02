<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\App;

class SetPanelLocale
{
    private const SUPPORTED_LOCALES = ['nl', 'en', 'fr', 'de'];
    private const DEFAULT_LOCALE    = 'en';

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Accept-Language', '');

        App::setLocale($this->resolveLocale($header));

        return $next($request);
    }

    private function resolveLocale(string $header): string
    {
        foreach ($this->parseAcceptLanguage($header) as $tag) {
            $primary = strtolower(explode('-', $tag)[0]);
            if (in_array($primary, self::SUPPORTED_LOCALES, true)) {
                return $primary;
            }
        }

        return self::DEFAULT_LOCALE;
    }

    private function parseAcceptLanguage(string $header): array
    {
        if (empty(trim($header))) {
            return [];
        }

        $tags = array_map('trim', explode(',', $header));

        usort($tags, function (string $a, string $b): int {
            return $this->quality($b) <=> $this->quality($a);
        });

        return array_map(fn(string $tag) => explode(';', $tag)[0], $tags);
    }

    private function quality(string $tag): float
    {
        if (preg_match('/;q=([\d.]+)/', $tag, $m)) {
            return (float) $m[1];
        }

        return 1.0;
    }
}
