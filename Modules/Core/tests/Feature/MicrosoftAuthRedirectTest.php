<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Modules\Core\Providers\CoreServiceProvider;
use Modules\Core\Services\Auth\FrontendRedirectService;
use ReflectionMethod;
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

    // Regression: CoreServiceProvider::registerConfig() used to call merge_config_from()
    // unconditionally on every boot, re-requiring Modules/Core/Config/config.php and
    // overwriting whatever was already in config('core.*') — including values baked in by
    // `php artisan config:cache`. In production this silently dropped entries from
    // core.frontend_redirect_urls (e.g. the safety/client frontends) any time the config
    // cache was warm, without ever throwing. The previous tests only used Config::set() at
    // runtime, which never exercises the configurationIsCached() path, so this went undetected.
    public function test_module_config_merge_is_skipped_when_config_is_cached(): void
    {
        Config::set('core.frontend_redirect_urls', ['https://sentinel.example/']);

        // Application::configurationIsCached() reads the 'config_loaded_from_cache'
        // container binding, registered once during bootstrap (same in Laravel 12.50
        // and 13.x). Writing the cache file after boot does not flip it, so bind the
        // flag directly to simulate a warm config cache.
        $this->app->instance('config_loaded_from_cache', true);

        $provider = new CoreServiceProvider($this->app);
        $method = new ReflectionMethod($provider, 'registerConfig');
        $method->setAccessible(true);
        $method->invoke($provider);

        $this->assertSame(
            ['https://sentinel.example/'],
            Config::get('core.frontend_redirect_urls'),
        );
    }
}
