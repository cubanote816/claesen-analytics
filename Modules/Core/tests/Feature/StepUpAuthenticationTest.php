<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\Notifications\VerifyEmailAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\Users\Pages\EditUser;
use Modules\Core\Models\User;
use Modules\Core\Services\StepUpAuthenticator;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * F2/CLA-464 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * StepUpAuthenticator's own contract (isAvailableFor/challengeFormComponents/
 * guardedSchema) plus the two Filament actions this ticket built on top of
 * it: EditUser's "changeRoles"/"revokeSessions" header actions. The
 * export/erase step-up wiring on ConsultationRequestResource is exercised
 * end-to-end by Modules/Website/tests/Feature/RetentionPolicyTest.php's
 * existing tests, unaffected since its actors have no MFA factor configured
 * (guardedSchema() degrades to the unguarded schema in that case, by
 * design — see StepUpAuthenticator's own docblock).
 */
final class StepUpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        foreach (['super_admin', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function superAdminWithAppAuth(): User
    {
        $user = User::factory()->create(['is_active' => true, 'password_set_at' => now()]);
        $user->assignRole('super_admin');

        $appAuth = app(AppAuthentication::class);
        $user->saveAppAuthenticationSecret($appAuth->generateSecret());
        $user->save();

        return $user->fresh();
    }

    private function superAdminWithEmailAuthOnly(): User
    {
        $user = User::factory()->create(['is_active' => true, 'password_set_at' => now()]);
        $user->assignRole('super_admin');
        $user->toggleEmailAuthentication(true);

        return $user->fresh();
    }

    // -------------------------------------------------------------------------
    // StepUpAuthenticator contract
    // -------------------------------------------------------------------------

    public function test_is_available_for_is_false_with_no_factor_configured(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(app(StepUpAuthenticator::class)->isAvailableFor($user));
        $this->assertSame([], app(StepUpAuthenticator::class)->challengeFormComponents($user));
    }

    public function test_challenge_form_components_prefers_app_auth_when_both_factors_are_configured(): void
    {
        $user = $this->superAdminWithAppAuth();
        $user->toggleEmailAuthentication(true);
        $user->save();
        $user = $user->fresh();

        $combined = app(StepUpAuthenticator::class)->challengeFormComponents($user);
        $appOnly = app(AppAuthentication::class)->getChallengeFormComponents($user);

        // Both providers key their code input identically ("code") — this
        // asserts the result is exactly the app-auth provider's own
        // component set (same count), never that PLUS the e-mail
        // provider's components appended on top (which would silently
        // collide on the same "code" key in the rendered schema).
        $this->assertCount(count($appOnly), $combined);
    }

    public function test_guarded_schema_returns_the_unguarded_schema_when_no_factor_is_configured(): void
    {
        $user = User::factory()->create();

        $result = app(StepUpAuthenticator::class)->guardedSchema($user, ['own-field-sentinel']);

        $this->assertSame(['own-field-sentinel'], $result);
    }

    public function test_send_challenge_if_needed_only_e_mails_a_code_when_email_is_the_only_factor(): void
    {
        Notification::fake();

        $appOnly = $this->superAdminWithAppAuth();
        app(StepUpAuthenticator::class)->sendChallengeIfNeeded($appOnly);
        Notification::assertNothingSent();

        $emailOnly = $this->superAdminWithEmailAuthOnly();
        app(StepUpAuthenticator::class)->sendChallengeIfNeeded($emailOnly);
        Notification::assertSentTo($emailOnly, VerifyEmailAuthentication::class);
    }

    // -------------------------------------------------------------------------
    // "changeRoles" action
    // -------------------------------------------------------------------------

    public function test_change_roles_with_a_valid_code_updates_the_users_roles(): void
    {
        Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $actor = $this->superAdminWithAppAuth();
        $target = User::factory()->create();
        $target->assignRole('viewer');
        $superAdminRoleId = Role::findByName('super_admin', 'web')->id;

        $this->actingAs($actor);

        $code = app(AppAuthentication::class)->getCurrentCode($actor, $actor->getAppAuthenticationSecret());

        Livewire::test(EditUser::class, ['record' => $target->getKey()])
            ->callAction('changeRoles', data: [
                'code' => $code,
                'roles' => [$superAdminRoleId],
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(['super_admin'], $target->fresh()->roles->pluck('name')->all());
    }

    public function test_change_roles_with_an_invalid_code_is_rejected_and_roles_are_unchanged(): void
    {
        Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $actor = $this->superAdminWithAppAuth();
        $target = User::factory()->create();
        $target->assignRole('viewer');

        $this->actingAs($actor);

        Livewire::test(EditUser::class, ['record' => $target->getKey()])
            ->callAction('changeRoles', data: [
                'code' => '000000',
                'roles' => [Role::findByName('super_admin', 'web')->id],
            ])
            ->assertHasActionErrors(['code']);

        $this->assertSame(['viewer'], $target->fresh()->roles->pluck('name')->all());
    }

    // -------------------------------------------------------------------------
    // "revokeSessions" action
    // -------------------------------------------------------------------------

    public function test_revoke_sessions_deletes_only_the_targeted_users_session_rows(): void
    {
        // phpunit.xml forces SESSION_DRIVER=array for the suite; production
        // (and this action's own ->visible() guard) uses 'database'.
        config(['session.driver' => 'database']);

        $actor = $this->superAdminWithAppAuth();
        $target = User::factory()->create();
        $otherUser = User::factory()->create();

        DB::table('sessions')->insert([
            ['id' => 'session-target', 'user_id' => $target->id, 'payload' => 'x', 'last_activity' => time()],
            ['id' => 'session-other', 'user_id' => $otherUser->id, 'payload' => 'x', 'last_activity' => time()],
        ]);

        $this->actingAs($actor);

        Livewire::test(EditUser::class, ['record' => $target->getKey()])
            ->callAction('revokeSessions');

        $this->assertFalse(DB::table('sessions')->where('id', 'session-target')->exists());
        $this->assertTrue(DB::table('sessions')->where('id', 'session-other')->exists());
    }
}
