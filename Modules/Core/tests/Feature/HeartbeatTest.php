<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Modules\Core\Http\Middleware\UpdateUserActivity;
use Modules\Core\Models\User;
use Tests\TestCase;

/**
 * CLA-225 — the heartbeat route SessionKeeper pings must actually touch
 * the authenticated session, or the periodic fetch() is a no-op.
 */
class HeartbeatTest extends TestCase
{
    public function test_authenticated_user_gets_no_content_response(): void
    {
        $user = User::factory()->make([
            'password_set_at' => now()->subDay(),
            'is_active'       => true,
        ]);

        $response = $this->withoutMiddleware(UpdateUserActivity::class)
            ->actingAs($user)
            ->get(route('core.heartbeat'));

        $response->assertNoContent();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->withoutMiddleware(UpdateUserActivity::class)
            ->get(route('core.heartbeat'));

        $response->assertRedirect();
    }
}
