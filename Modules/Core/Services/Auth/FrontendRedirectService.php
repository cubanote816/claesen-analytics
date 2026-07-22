<?php

declare(strict_types=1);

namespace Modules\Core\Services\Auth;

class FrontendRedirectService
{
    public function resolve(?string $candidate): ?string
    {
        $candidateOrigin = $this->origin($candidate);

        if ($candidateOrigin === null) {
            return null;
        }

        foreach (config('core.frontend_redirect_urls', []) as $allowedUrl) {
            if ($this->origin($allowedUrl) !== $candidateOrigin) {
                continue;
            }

            return rtrim((string) $allowedUrl, '/').'/';
        }

        return null;
    }

    public function fallback(): string
    {
        foreach (config('core.frontend_redirect_urls', []) as $allowedUrl) {
            if ($this->origin($allowedUrl) !== null) {
                return rtrim((string) $allowedUrl, '/').'/';
            }
        }

        return app()->environment('production')
            ? 'https://service.claesen-verlichting.be/'
            : 'http://localhost:5173/';
    }

    private function origin(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $parts = parse_url(trim($url));

        if (! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$scheme}://{$host}{$port}";
    }
}
