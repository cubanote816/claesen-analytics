<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// CLA-347: a deactivated user must lose access immediately (revoked tokens and
// database sessions), not merely fail future login attempts.
class UserDeactivationRevokesAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivating_a_user_revokes_their_tokens_and_database_sessions(): void
    {
        // Tests force SESSION_DRIVER=array (phpunit.xml) to avoid touching the
        // sessions table for unrelated tests; this one simulates the real
        // production driver so the observer's guard actually runs.
        Config::set('session.driver', 'database');

        $user = UserFactory::new()->create(['is_active' => true]);
        $user->createToken('test-device');
        DB::table('sessions')->insert([
            'id' => 'sess-'.$user->id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('x'),
            'last_activity' => time(),
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('sessions', ['user_id' => $user->id]);

        $user->update(['is_active' => false]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }

    public function test_updating_unrelated_fields_does_not_revoke_tokens(): void
    {
        $user = UserFactory::new()->create(['is_active' => true]);
        $user->createToken('test-device');

        $user->update(['name' => 'Updated Name']);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_reactivating_a_user_does_not_error_and_does_not_touch_tokens(): void
    {
        $user = UserFactory::new()->create(['is_active' => false]);
        $user->createToken('test-device');

        $user->update(['is_active' => true]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
