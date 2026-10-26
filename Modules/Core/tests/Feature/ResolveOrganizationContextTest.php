<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Core\Http\Middleware\ResolveOrganizationContext;
use Modules\Core\Http\Middleware\UpdateUserActivity;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Core\Services\OrganizationContext;
use Tests\TestCase;

/**
 * F1/P4 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Covers the part of CLA-460 that's in scope right now: OrganizationContext
 * resolved server-side and revalidated per request. Still 100% inert — no
 * assertion here about restriction/redirect/404, because this middleware
 * doesn't do any of that yet (P5).
 */
final class ResolveOrganizationContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_the_context_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $middleware = new ResolveOrganizationContext;
        $middleware->handle(Request::create('/'), fn () => response('ok'));

        $this->assertTrue(app(OrganizationContext::class)->resolve()->is($user->organization));
        $this->assertSame(Organization::claesenId(), app(OrganizationContext::class)->id());
    }

    public function test_it_is_a_no_op_for_a_guest(): void
    {
        $middleware = new ResolveOrganizationContext;
        $response = $middleware->handle(Request::create('/'), fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull(app(OrganizationContext::class)->resolve());
    }

    public function test_an_authenticated_web_request_resolves_the_context_without_regressing_the_route(): void
    {
        $user = User::factory()->make([
            'password_set_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $response = $this->withoutMiddleware(UpdateUserActivity::class)
            ->actingAs($user)
            ->get(route('core.heartbeat'));

        $response->assertNoContent();
    }
}
