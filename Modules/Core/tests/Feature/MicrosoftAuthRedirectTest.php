<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Modules\Core\Services\Auth\FrontendRedirectService;
use Tests\TestCase;

class MicrosoftAuthRedirectTest extends TestCase
{
    // Regression for a config() misuse: config('key', $default) only falls back to $default
    // when the key is absent, not when it resolves to null. 'services.azure.public_redirect'
    // always exists (mapped from MICROSOFT_AUTH_PUBLIC_REDIRECT), so an empty env var used to
    // resolve to null and crash the API redirect route with a TypeError instead of falling
    // back to 'services.azure.redirect'.
    public function test_api_redirect_falls_back_when_public_redirect_is_unset(): void
    {
        Config::set('services.azure.public_redirect', null);
        Config::set('services.azure.redirect', 'http://localhost:8000/auth/microsoft/callback');
        Config::set('services.azure.client_id', 'test-client-id');
        Config::set('services.azure.tenant', 'test-tenant-id');

        $response = $this->get('/api/v1/auth/microsoft/redirect');

        $response->assertStatus(302);
    }

    // CLA-XXX: MICROSOFT_AUTH_PUBLIC_REDIRECT is a single static value, so it can only ever
    // match the Azure-registered callback for one frontend. redirect() now derives the
    // per-frontend callback from custom_redirect_url/referer instead, so each configured
    // frontend (Safety, Sport, ...) gets sent back to its own registered redirect_uri.
    public function test_api_redirect_uses_the_callback_for_the_requesting_frontend(): void
    {
        Config::set('services.azure.public_redirect', 'https://safety.claesen-verlichting.be/api/v1/auth/microsoft/callback');
        Config::set('services.azure.redirect', 'https://backoffice.claesen.local/auth/microsoft/callback');
        Config::set('services.azure.client_id', 'test-client-id');
        Config::set('services.azure.tenant', 'test-tenant-id');
        Config::set('core.frontend_redirect_urls', [
            'https://safety.claesen-verlichting.be/',
            'https://service.claesen-verlichting.be/',
        ]);

        $response = $this->get('/api/v1/auth/microsoft/redirect?custom_redirect_url='.urlencode('https://service.claesen-verlichting.be/app/dashboard'));

        $response->assertStatus(302);
        $this->assertStringContainsString(
            urlencode('https://service.claesen-verlichting.be/api/v1/auth/microsoft/callback'),
            (string) $response->headers->get('Location'),
        );
    }

    public function test_frontend_redirects_are_limited_to_configured_origins(): void
    {
        Config::set('core.frontend_redirect_urls', [
            'https://service.claesen-verlichting.be/',
            'http://localhost:5173/',
        ]);

        $redirects = app(FrontendRedirectService::class);

        $this->assertSame(
            'https://service.claesen-verlichting.be/',
            $redirects->resolve('https://service.claesen-verlichting.be/work-orders/12'),
        );
        $this->assertSame('http://localhost:5173/', $redirects->resolve('http://localhost:5173/anything'));
        $this->assertNull($redirects->resolve('https://evil.example/steal-session'));
        $this->assertNull($redirects->resolve('javascript:alert(1)'));
        $this->assertNull($redirects->resolve('https://user:password@service.claesen-verlichting.be/'));
    }
}
