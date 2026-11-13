<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Database\Factories\UserFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Cafca\Models\Employee;
use Modules\Core\Filament\Resources\Users\Pages\CreateUser;
use Modules\Core\Filament\Resources\Users\Pages\EditUser;
use Modules\Core\Models\User;
use Modules\Core\Notifications\AccountActivationNotification;
use Modules\Core\Services\PasswordResetService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-685: an External worker gets the activation link by email when the account
 * is created (72 hours), and an admin can resend it from the Edit page. The
 * forgot-password flow and `core:issue-activation-code` keep their 60 minutes.
 */
final class AccountActivationEmailTest extends TestCase
{
    use RefreshDatabase;

    private Role $viewerRole;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->viewerRole = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = UserFactory::new()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function createExternal(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'account_type' => 'external',
                'external_name' => 'Jan Freelance',
                'external_email' => 'jan.freelance@gmail.com',
                'role_ids' => [$this->viewerRole->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    private function pendingUser(array $overrides = []): User
    {
        return UserFactory::new()->create(array_merge([
            'email' => 'pending@gmail.com',
            'is_active' => true,
            'password' => null,
            'password_set_at' => null,
            'microsoft_id' => null,
        ], $overrides));
    }

    /** The activation link emailed to this user, as the notification builds it. */
    private function sentUrl(User $user): string
    {
        $url = null;

        Notification::assertSentTo($user, AccountActivationNotification::class, function ($notification) use ($user, &$url): bool {
            $url = $notification->toMail($user)->actionUrl;

            return true;
        });

        return (string) $url;
    }

    private function codeFrom(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return (string) $query['token'];
    }

    // S1
    public function test_creating_an_external_worker_sends_one_activation_email(): void
    {
        $this->createExternal();

        $user = User::where('email', 'jan.freelance@gmail.com')->firstOrFail();

        Notification::assertSentToTimes($user, AccountActivationNotification::class, 1);
        Notification::assertCount(1);
    }

    // S1, S3
    public function test_the_stored_code_lasts_72_hours(): void
    {
        $this->createExternal();

        $user = User::where('email', 'jan.freelance@gmail.com')->firstOrFail();

        $this->assertNotNull($user->activation_code_hash);
        $this->assertEqualsWithDelta(72 * 60, now()->diffInMinutes($user->activation_code_expires_at), 2);
    }

    // S1, S4
    public function test_the_emailed_link_really_sets_the_first_password(): void
    {
        $this->createExternal();

        $user = User::where('email', 'jan.freelance@gmail.com')->firstOrFail();
        $code = $this->codeFrom($this->sentUrl($user));

        $this->assertSame(hash('sha256', $code), $user->activation_code_hash);

        app(PasswordResetService::class)->resetPassword($code, 'NewSecret123!');

        $this->assertTrue($user->refresh()->hasCompletedPasswordSetup());
    }

    // S2
    public function test_creating_an_internal_employee_sends_no_activation_email(): void
    {
        $employee = Employee::create([
            'id' => 'EMP-6850', 'name' => 'Inge Intern',
            'email' => 'inge.intern@claesen-verlichting.be', 'fl_active' => true,
        ]);

        Livewire::test(CreateUser::class)
            ->fillForm(['employee_id' => $employee->id, 'role_ids' => [$this->viewerRole->id]])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(User::where('employee_id', 'EMP-6850')->exists());
        Notification::assertNothingSent();
    }

    // S2
    public function test_creating_a_client_contact_sends_no_activation_email(): void
    {
        $page = app(CreateUser::class);
        $record = new \ReflectionMethod($page, 'handleRecordCreation');
        $record->setAccessible(true);

        $user = $record->invoke($page, [
            'name' => 'Cleo Client', 'email' => 'cleo@client.be', 'password' => null, 'password_set_at' => null,
            'role_ids' => [Role::findByName('client')->id],
        ]);
        $page->record = $user;
        $after = new \ReflectionMethod($page, 'afterCreate');
        $after->setAccessible(true);
        $after->invoke($page);

        Notification::assertNothingSent();
    }

    // S1: a creation that rolls back never sends.
    public function test_a_rolled_back_creation_sends_nothing(): void
    {
        // The role vanishes right after the user row is written, so syncRoles() throws
        // inside handleRecordCreation()'s transaction and the user insert is rolled back.
        User::created(function (): void {
            Role::query()->whereKey($this->viewerRole->id)->delete();
        });

        try {
            $this->createExternal();
            $this->fail('The creation should have failed.');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(ValidationException::class, $e);
        }

        $this->assertFalse(User::where('email', 'jan.freelance@gmail.com')->exists());
        Notification::assertNothingSent();
    }

    // S3
    public function test_forgot_password_and_the_command_keep_60_minutes(): void
    {
        $user = UserFactory::new()->create([
            'email' => 'done@gmail.com', 'is_active' => true, 'microsoft_id' => null,
            'password' => 'Secret123!', 'password_set_at' => now(),
        ]);

        app(PasswordResetService::class)->sendResetLink('done@gmail.com');

        $this->assertEqualsWithDelta(60, now()->diffInMinutes($user->refresh()->activation_code_expires_at), 2);

        $pending = $this->pendingUser();
        $this->assertSame(0, Artisan::call('core:issue-activation-code', ['email' => $pending->email]));
        $this->assertEqualsWithDelta(60, now()->diffInMinutes($pending->refresh()->activation_code_expires_at), 2);
    }

    // S4
    public function test_the_mail_has_the_link_the_72_hours_and_the_private_network_notice(): void
    {
        $user = $this->pendingUser(['name' => 'Jan Freelance']);
        $url = Filament::getPanel('admin')->getResetPasswordUrl('abc', $user);
        $notification = new AccountActivationNotification($url);

        $expected = [
            'en' => ['72 hours', 'VPN or office', 'administrator'],
            'nl' => ['72 uur', 'VPN of op kantoor', 'beheerder'],
        ];

        foreach ($expected as $locale => $phrases) {
            App::setLocale($locale);
            $mail = $notification->toMail($user);
            $text = implode("\n", [$mail->subject, $mail->greeting, ...$mail->introLines, ...$mail->outroLines]);

            $this->assertSame($url, $mail->actionUrl, $locale);
            $this->assertStringContainsString('Jan Freelance', $text, $locale);
            foreach ($phrases as $phrase) {
                $this->assertStringContainsString($phrase, $text, $locale.': '.$phrase);
            }
        }
    }

    // S5
    public function test_the_resend_action_is_visible_only_for_pending_local_active_accounts(): void
    {
        $cases = [
            'pending' => [[], true],
            'activated' => [['password' => 'Secret123!', 'password_set_at' => now()], false],
            'microsoft' => [['microsoft_id' => 'azure-oid'], false],
            'inactive' => [['is_active' => false], false],
        ];

        foreach ($cases as $label => [$overrides, $visible]) {
            $user = $this->pendingUser(array_merge(['email' => $label.'@gmail.com'], $overrides));

            $test = Livewire::test(EditUser::class, ['record' => $user->getKey()]);

            $visible
                ? $test->assertActionVisible('resendActivationEmail')
                : $test->assertActionHidden('resendActivationEmail');
        }
    }

    // S5
    public function test_resending_replaces_the_code_and_sends_one_email(): void
    {
        $user = $this->pendingUser();
        $old = app(PasswordResetService::class)->issueActivationCode($user);

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->callAction('resendActivationEmail')
            ->assertNotified();

        Notification::assertSentToTimes($user, AccountActivationNotification::class, 1);
        Notification::assertCount(1);

        $new = $this->codeFrom($this->sentUrl($user));
        $this->assertNotSame($old, $new);
        $this->assertEqualsWithDelta(72 * 60, now()->diffInMinutes($user->refresh()->activation_code_expires_at), 2);

        try {
            app(PasswordResetService::class)->resetPassword($old, 'NewSecret123!');
            $this->fail('The previous code should no longer work.');
        } catch (ValidationException) {
            $this->assertFalse($user->refresh()->hasCompletedPasswordSetup());
        }

        app(PasswordResetService::class)->resetPassword($new, 'NewSecret123!');
        $this->assertTrue($user->refresh()->hasCompletedPasswordSetup());
    }

    // CLA-685 verify: the send is deferred to the outermost commit, so rolling back
    // an enclosing transaction cancels it after the account was already created.
    public function test_an_outer_rollback_cancels_the_activation_email(): void
    {
        DB::beginTransaction();

        try {
            $this->createExternal();
            Notification::assertNothingSent();
            $user = User::where('email', 'jan.freelance@gmail.com')->firstOrFail();
            $this->assertNull($user->activation_code_hash);
        } finally {
            DB::rollBack();
        }

        $this->assertFalse(User::where('email', 'jan.freelance@gmail.com')->exists());
        Notification::assertNothingSent();
    }

    // CLA-685 verify: forcing the resend action on an ineligible account (activated,
    // Microsoft, inactive) issues no code and sends nothing, whichever layer refuses.
    public function test_a_forced_resend_on_an_ineligible_account_changes_nothing(): void
    {
        $cases = [
            ['password' => 'Secret123!', 'password_set_at' => now()],
            ['microsoft_id' => 'azure-oid'],
            ['is_active' => false],
        ];

        foreach ($cases as $i => $overrides) {
            $user = $this->pendingUser(array_merge(['email' => "blocked{$i}@example.test"], $overrides));
            app(PasswordResetService::class)->issueActivationCode($user);
            $before = $user->refresh()->getRawOriginal();

            try {
                Livewire::test(EditUser::class, ['record' => $user->getKey()])
                    ->call('mountAction', 'resendActivationEmail')
                    ->call('callMountedAction');
            } catch (\Throwable) {
                // Refusing by exception is as good as refusing silently.
            }

            $this->assertSame($before, $user->refresh()->getRawOriginal(), json_encode($overrides));
            Notification::assertNothingSent();
        }
    }
}
