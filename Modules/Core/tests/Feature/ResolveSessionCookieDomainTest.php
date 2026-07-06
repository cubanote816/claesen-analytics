<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Tests\TestCase;

/**
 * CLA-232/233 — backoffice.claesen.local (Filament) y *.claesen-verlichting.be
 * (service.claesen-verlichting, Sanctum SPA) no pueden compartir un único
 * SESSION_DOMAIN estático. La cookie de sesión debe resolverse por host.
 */
class ResolveSessionCookieDomainTest extends TestCase
{
    public function test_claesen_verlichting_be_host_gets_shared_parent_domain(): void
    {
        $this->get('http://backend.claesen-verlichting.be/up');

        $this->assertSame('.claesen-verlichting.be', config('session.domain'));
    }

    public function test_service_subdomain_also_gets_shared_parent_domain(): void
    {
        $this->get('http://service.claesen-verlichting.be/up');

        $this->assertSame('.claesen-verlichting.be', config('session.domain'));
    }

    public function test_backoffice_local_host_gets_no_fixed_domain(): void
    {
        $this->get('http://backoffice.claesen.local/up');

        $this->assertNull(config('session.domain'));
    }

    public function test_unrelated_host_gets_no_fixed_domain(): void
    {
        $this->get('http://localhost/up');

        $this->assertNull(config('session.domain'));
    }
}
