<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Tests\TestCase;

/**
 * CLA-232/233 — backoffice.claesen.local (Filament) y service.claesen-verlichting.be
 * (Sanctum SPA, llama a backend.claesen-verlichting.be) no pueden compartir un
 * único SESSION_DOMAIN estático. La cookie de sesión se resuelve por
 * Origin/Referer (igual que Sanctum::fromFrontend()), no por Host — el túnel
 * que trae el tráfico público reescribe el Host interno a
 * backoffice.claesen.local antes de llegar a Laravel.
 */
class ResolveSessionCookieDomainTest extends TestCase
{
    public function test_stateful_spa_referer_gets_shared_parent_domain(): void
    {
        $this->withHeaders(['Referer' => 'https://service.claesen-verlichting.be/'])
            ->get('http://backoffice.claesen.local/up');

        $this->assertSame('.claesen-verlichting.be', config('session.domain'));
    }

    public function test_stateful_spa_origin_gets_shared_parent_domain(): void
    {
        $this->withHeaders(['Origin' => 'https://service.claesen-verlichting.be'])
            ->get('http://backoffice.claesen.local/up');

        $this->assertSame('.claesen-verlichting.be', config('session.domain'));
    }

    public function test_host_rewritten_by_tunnel_still_resolves_by_referer(): void
    {
        // Reproduce el proxy real: el Host que ve Laravel es siempre
        // backoffice.claesen.local, incluso para trafico de la API publica
        // de la SPA. Solo el Referer delata el origen real.
        $this->withHeaders(['Referer' => 'https://service.claesen-verlichting.be/'])
            ->get('http://backoffice.claesen.local/api/v1/login');

        $this->assertSame('.claesen-verlichting.be', config('session.domain'));
    }

    public function test_filament_navigation_without_frontend_referer_gets_no_fixed_domain(): void
    {
        $this->get('http://backoffice.claesen.local/up');

        $this->assertNull(config('session.domain'));
    }

    public function test_unrelated_referer_gets_no_fixed_domain(): void
    {
        $this->withHeaders(['Referer' => 'https://login.microsoftonline.com/'])
            ->get('http://backoffice.claesen.local/up');

        $this->assertNull(config('session.domain'));
    }

    public function test_local_dev_stateful_origin_gets_no_fixed_domain(): void
    {
        // localhost:5173/5174 are in SANCTUM_STATEFUL_DOMAINS for local dev (Claesen-Sport,
        // service.claesen-verlichting), so EnsureFrontendRequestsAreStateful::fromFrontend()
        // is true here too — but they must NOT get the production ".claesen-verlichting.be"
        // cookie domain, since a browser on localhost will never send that cookie back.
        $this->withHeaders(['Origin' => 'http://localhost:5174'])
            ->get('http://localhost:8000/up');

        $this->assertNull(config('session.domain'));
    }

    public function test_lookalike_domain_gets_no_fixed_domain(): void
    {
        $this->withHeaders(['Origin' => 'https://notclaesen-verlichting.be'])
            ->get('http://backoffice.claesen.local/up');

        $this->assertNull(config('session.domain'));
    }
}
