<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Models\Site;
use Modules\Core\Services\OrganizationContext;
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
            if (in_array($primary, $this->supportedLocales(), true)) {
                return $primary;
            }
        }

        return $this->defaultLocale();
    }

    /**
     * F3/CLA-471: for a resolved site other than Claesen, use its own
     * locales/default_locale instead of the hardcoded list above — that list
     * stays the exact behaviour for Claesen (the only real site today) and
     * for any context this middleware runs in without a site resolved
     * (Modules\Core\Http\Middleware\ResolveRequestSite always resolves one
     * on the routes this middleware is actually applied to, but this
     * middleware class predates that guarantee and shouldn't assume it).
     */
    private function resolveNonClaesenSite(): ?Site
    {
        $site = app(OrganizationContext::class)->site();

        return ($site !== null && $site->id !== Site::claesenId()) ? $site : null;
    }

    /**
     * @return array<int, string>
     */
    private function supportedLocales(): array
    {
        $site = $this->resolveNonClaesenSite();

        return ($site !== null && filled($site->locales)) ? $site->locales : self::SUPPORTED_LOCALES;
    }

    private function defaultLocale(): string
    {
        $site = $this->resolveNonClaesenSite();

        return ($site !== null && filled($site->default_locale)) ? $site->default_locale : self::DEFAULT_LOCALE;
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
