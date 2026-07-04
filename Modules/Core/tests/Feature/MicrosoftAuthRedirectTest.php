<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Support\Facades\Config;
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
}
