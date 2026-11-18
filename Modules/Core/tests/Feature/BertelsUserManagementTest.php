<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Filament\Bertels\Resources\Users\BertelsUserResource;
use App\Filament\Bertels\Resources\Users\Pages\CreateBertelsUser;
use App\Filament\Bertels\Resources\Users\Pages\EditBertelsUser;
use App\Filament\Bertels\Resources\Users\Pages\ListBertelsUsers;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\Users\Pages\CreateUser;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Modules\Core\Notifications\AccountActivationNotification;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * CLA-704 T1 (S1-S5): the Users section of the Electro Bertels panel.
 */
final class BertelsUserManagementTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    private Organization $bertels;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();

        foreach (['super_admin', 'admin', 'viewer', 'client', 'technician'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->bertels = $this->bertelsFixtureOrganization(['slug' => 'electro-bertels', 'name' => 'Electro Bertels']);
        $this->bertelsFixtureSite($this->bertels, ['key' => 'electro-bertels']);

        $this->superAdmin = $this->superAdminWithAppAuth();
        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel(Filament::getPanel('bertels'));
    }

    private function superAdminWithAppAuth(): User
    {
        $user = $this->claesenUser(['is_active' => true, 'password_set_at' => now()]);
        $user->assignRole('super_admin');
        $user->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());
        $user->save();

        return $user->fresh();
    }

    private function bertelsUser(array $attributes = [], string $role = 'viewer'): User
    {
        $user = $this->bertelsFixtureUser($this->bertels, $attributes);
        $user->assignRole($role);

        return $user;
    }

    private function roleId(string $name): int
    {
        return Role::findByName($name, 'web')->id;
    }

    private function stepUpCode(): string
    {
        return app(AppAuthentication::class)->getCurrentCode($this->superAdmin, $this->superAdmin->getAppAuthenticationSecret());
    }

    private function microsoftForm(array $overrides = []): array
    {
        return array_merge([
            'account_type' => 'microsoft',
            'name' => 'Marie Microsoft',
            'email' => 'Marie@ElectroBertels.be',
            'role_ids' => [$this->roleId('viewer')],
        ], $overrides);
    }

    private function localForm(array $overrides = []): array
    {
        return array_merge([
            'account_type' => 'local',
            'name' => 'Luc Local',
            'email' => 'luc@gmail.com',
            'role_ids' => [$this->roleId('admin')],
        ], $overrides);
    }

    // ------------------------------------------------------------------ S1

    // S1
    public function test_super_admin_sees_and_opens_the_section(): void
    {
        $this->assertTrue(BertelsUserResource::canAccess());
        $this->get('/bertels/users')->assertSuccessful();
        $this->get('/bertels/users/create')->assertSuccessful();
    }

    public static function nonSuperAdmins(): array
    {
        return [
            'bertels admin' => ['bertels', 'admin'],
            'bertels viewer' => ['bertels', 'viewer'],
            'claesen admin' => ['claesen', 'admin'],
            'claesen viewer' => ['claesen', 'viewer'],
        ];
    }

    // S1
    #[DataProvider('nonSuperAdmins')]
    public function test_nobody_but_super_admin_gets_in(string $company, string $role): void
    {
        $user = $company === 'bertels' ? $this->bertelsUser([], $role) : $this->claesenUser();
        if ($company === 'claesen') {
            $user->assignRole($role);
        }
        $target = $this->bertelsUser();

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('bertels'));

        $this->assertFalse(BertelsUserResource::canAccess());
        $this->assertFalse(BertelsUserResource::canCreate());
        $this->get('/bertels/users')->assertForbidden();
        $this->get('/bertels/users/create')->assertForbidden();
        $this->get("/bertels/users/{$target->getKey()}/edit")->assertForbidden();
    }

    // ------------------------------------------------------------------ S2

    // S2
    #[DataProvider('enforceFlag')]
    public function test_the_list_shows_only_bertels_users(bool $enforce): void
    {
        config(['organizations.enforce' => $enforce]);

        $bertelsUser = $this->bertelsUser();
        $claesenUser = $this->claesenUser();

        Livewire::test(ListBertelsUsers::class)
            ->assertCanSeeTableRecords([$bertelsUser])
            ->assertCanNotSeeTableRecords([$claesenUser, $this->superAdmin]);
    }

    public static function enforceFlag(): array
    {
        return ['enforce off' => [false], 'enforce on' => [true]];
    }

    // S2
    public function test_a_claesen_users_edit_url_is_a_404_in_the_bertels_resource(): void
    {
        $claesenUser = $this->claesenUser();
        $bertelsUser = $this->bertelsUser();

        $this->get("/bertels/users/{$claesenUser->getKey()}/edit")->assertNotFound();
        $this->get("/bertels/users/{$bertelsUser->getKey()}/edit")->assertSuccessful();
    }

    // S2
    public function test_users_without_organization_are_not_listed(): void
    {
        $orphan = $this->bertelsFixtureUser($this->bertels);
        DB::table('users')->where('id', $orphan->id)->update(['organization_id' => null]);

        Livewire::test(ListBertelsUsers::class)->assertCanNotSeeTableRecords([$orphan->fresh()]);
    }

    // S2
    public function test_without_a_bertels_site_the_section_is_unavailable_and_creates_nothing(): void
    {
        Site::query()->where('key', 'electro-bertels')->delete();
        $before = User::count();

        $this->assertFalse(BertelsUserResource::canAccess());
        $this->get('/bertels/users')->assertForbidden();
        $this->get('/bertels/users/create')->assertForbidden();
        $this->assertSame($before, User::count());
    }

    // ------------------------------------------------------------------ S3

    // S3
    public function test_a_microsoft_account_is_created_in_bertels_without_password_or_mail(): void
    {
        Livewire::test(CreateBertelsUser::class)
            ->fillForm($this->microsoftForm())
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'marie@electrobertels.be')->firstOrFail();
        $this->assertSame($this->bertels->id, $user->organization_id);
        $this->assertSame('Marie Microsoft', $user->name);
        $this->assertNull($user->password);
        $this->assertNull($user->password_set_at);
        $this->assertNull($user->microsoft_id);
        $this->assertSame(['viewer'], $user->getRoleNames()->all());
        Notification::assertNothingSent();
    }

    // S3
    public function test_a_microsoft_account_with_another_domain_is_rejected(): void
    {
        $before = User::count();

        Livewire::test(CreateBertelsUser::class)
            ->fillForm($this->microsoftForm(['email' => 'marie@gmail.com']))
            ->call('create')
            ->assertHasFormErrors(['email']);

        $this->assertSame($before, User::count());
        Notification::assertNothingSent();
    }

    // S3
    public function test_a_local_account_is_created_in_bertels_and_emailed_once_with_a_bertels_link(): void
    {
        Livewire::test(CreateBertelsUser::class)
            ->fillForm($this->localForm())
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'luc@gmail.com')->firstOrFail();
        $this->assertSame($this->bertels->id, $user->organization_id);
        $this->assertNull($user->password);
        $this->assertNull($user->password_set_at);
        $this->assertSame(['admin'], $user->getRoleNames()->all());
        $this->assertNotNull($user->activation_code_hash);

        Notification::assertSentToTimes($user, AccountActivationNotification::class, 1);
        Notification::assertCount(1);

        $url = null;
        Notification::assertSentTo($user, AccountActivationNotification::class, function ($n) use ($user, &$url): bool {
            $url = $n->toMail($user)->actionUrl;

            return true;
        });
        $this->assertStringStartsWith('/bertels/', (string) parse_url($url, PHP_URL_PATH));
    }

    // S3
    public function test_a_local_account_with_a_bertels_domain_email_is_rejected(): void
    {
        $before = User::count();

        Livewire::test(CreateBertelsUser::class)
            ->fillForm($this->localForm(['email' => 'luc@electrobertels.be']))
            ->call('create')
            ->assertHasFormErrors(['email']);

        $this->assertSame($before, User::count());
        Notification::assertNothingSent();
    }

    // S3
    public function test_an_email_already_in_use_is_rejected(): void
    {
        $this->claesenUser(['email' => 'luc@gmail.com']);
        $before = User::count();

        Livewire::test(CreateBertelsUser::class)
            ->fillForm($this->localForm())
            ->call('create')
            ->assertHasFormErrors(['email']);

        $this->assertSame($before, User::count());
    }

    // S3 (preserve)
    public function test_claesens_activation_link_still_points_to_the_admin_panel(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'account_type' => 'external',
                'external_name' => 'Jan Freelance',
                'external_email' => 'jan.freelance@gmail.com',
                'role_ids' => [$this->roleId('viewer')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'jan.freelance@gmail.com')->firstOrFail();
        $this->assertSame(Organization::claesenId(), $user->organization_id);

        $url = null;
        Notification::assertSentTo($user, AccountActivationNotification::class, function ($n) use ($user, &$url): bool {
            $url = $n->toMail($user)->actionUrl;

            return true;
        });
        $this->assertStringNotContainsString('/bertels/', (string) parse_url($url, PHP_URL_PATH));
        $this->assertSame(
            parse_url(Filament::getPanel('admin')->getResetPasswordUrl('x', $user), PHP_URL_PATH),
            parse_url($url, PHP_URL_PATH),
        );
    }

    // ------------------------------------------------------------------ S4

    public static function forbiddenRoles(): array
    {
        return ['super_admin' => ['super_admin'], 'client' => ['client'], 'technician' => ['technician']];
    }

    // S4
    public function test_viewer_and_admin_are_accepted_on_create(): void
    {
        Livewire::test(CreateBertelsUser::class)
            ->fillForm($this->localForm(['role_ids' => [$this->roleId('viewer'), $this->roleId('admin')]]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['admin', 'viewer'], User::where('email', 'luc@gmail.com')->firstOrFail()->getRoleNames()->all());
    }

    // S4
    #[DataProvider('forbiddenRoles')]
    public function test_other_roles_are_rejected_on_create_and_nothing_is_stored(string $role): void
    {
        $before = User::count();

        Livewire::test(CreateBertelsUser::class)
            ->fillForm($this->localForm(['role_ids' => [$this->roleId('viewer'), $this->roleId($role)]]))
            ->call('create')
            ->assertHasFormErrors();

        $this->assertSame($before, User::count());
        Notification::assertNothingSent();
    }

    // S4: the server-side allowlist holds even when the form's own option check is bypassed.
    #[DataProvider('forbiddenRoles')]
    public function test_the_create_page_itself_refuses_other_roles(string $role): void
    {
        $page = app(CreateBertelsUser::class);
        $method = new \ReflectionMethod($page, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        try {
            $method->invoke($page, $this->localForm(['role_ids' => [$this->roleId($role)]]));
            $this->fail('A role outside the allowlist was not refused.');
        } catch (ValidationException $e) {
            $this->assertSame(['Only the Viewer and Admin roles can be assigned.'], $e->errors()['data.role_ids'] ?? null);
        }
    }

    // S4
    public function test_the_create_form_offers_only_viewer_and_admin(): void
    {
        $this->assertSame(
            ['admin', 'viewer'],
            collect(BertelsUserResource::assignableRoleOptions())->map(fn ($label) => strtolower($label))->sort()->values()->all(),
        );
    }

    // S4
    public function test_change_roles_with_a_valid_code_accepts_viewer_and_admin(): void
    {
        $target = $this->bertelsUser();

        Livewire::test(EditBertelsUser::class, ['record' => $target->getKey()])
            ->callAction('changeRoles', data: ['code' => $this->stepUpCode(), 'roles' => [$this->roleId('admin')]])
            ->assertHasNoActionErrors();

        $this->assertSame(['admin'], $target->fresh()->roles->pluck('name')->all());
    }

    // S4
    #[DataProvider('forbiddenRoles')]
    public function test_change_roles_rejects_other_roles_and_stores_nothing(string $role): void
    {
        $target = $this->bertelsUser();

        Livewire::test(EditBertelsUser::class, ['record' => $target->getKey()])
            ->callAction('changeRoles', data: ['code' => $this->stepUpCode(), 'roles' => [$this->roleId($role)]])
            ->assertHasActionErrors();

        $this->assertSame(['viewer'], $target->fresh()->roles->pluck('name')->all());
    }

    // S4 (step-up kept)
    public function test_change_roles_with_a_wrong_code_is_rejected(): void
    {
        $target = $this->bertelsUser();

        Livewire::test(EditBertelsUser::class, ['record' => $target->getKey()])
            ->callAction('changeRoles', data: ['code' => '000000', 'roles' => [$this->roleId('admin')]])
            ->assertHasActionErrors(['code']);

        $this->assertSame(['viewer'], $target->fresh()->roles->pluck('name')->all());
    }

    // ------------------------------------------------------------------ S5

    // S5
    public function test_resend_activation_emails_a_bertels_link_to_a_pending_local_account(): void
    {
        $user = $this->bertelsUser(['email' => 'pending@gmail.com', 'password' => null, 'password_set_at' => null, 'microsoft_id' => null]);

        Livewire::test(EditBertelsUser::class, ['record' => $user->getKey()])
            ->assertActionVisible('resendActivationEmail')
            ->callAction('resendActivationEmail')
            ->assertNotified();

        $url = null;
        Notification::assertSentToTimes($user, AccountActivationNotification::class, 1);
        Notification::assertSentTo($user, AccountActivationNotification::class, function ($n) use ($user, &$url): bool {
            $url = $n->toMail($user)->actionUrl;

            return true;
        });
        $this->assertStringStartsWith('/bertels/', (string) parse_url($url, PHP_URL_PATH));
    }

    // S5
    public function test_resend_activation_is_hidden_for_activated_and_microsoft_accounts(): void
    {
        $activated = $this->bertelsUser(['email' => 'done@gmail.com']);
        $microsoft = $this->bertelsUser(['email' => 'ms@electrobertels.be', 'password' => null, 'password_set_at' => null, 'microsoft_id' => 'oid']);

        Livewire::test(EditBertelsUser::class, ['record' => $activated->getKey()])->assertActionHidden('resendActivationEmail');
        // A corporate-domain account that has not signed in yet has no microsoft_id either.
        $corporate = $this->bertelsUser(['email' => 'new@electrobertels.be', 'password' => null, 'password_set_at' => null, 'microsoft_id' => null]);

        Livewire::test(EditBertelsUser::class, ['record' => $microsoft->getKey()])->assertActionHidden('resendActivationEmail');
        Livewire::test(EditBertelsUser::class, ['record' => $corporate->getKey()])->assertActionHidden('resendActivationEmail');
    }

    // S5
    public function test_revoke_sessions_deletes_only_that_bertels_users_sessions(): void
    {
        config(['session.driver' => 'database']);
        $target = $this->bertelsUser();
        $other = $this->bertelsUser();

        DB::table('sessions')->insert([
            ['id' => 'session-target', 'user_id' => $target->id, 'payload' => 'x', 'last_activity' => time()],
            ['id' => 'session-other', 'user_id' => $other->id, 'payload' => 'x', 'last_activity' => time()],
        ]);

        Livewire::test(EditBertelsUser::class, ['record' => $target->getKey()])->callAction('revokeSessions');

        $this->assertFalse(DB::table('sessions')->where('id', 'session-target')->exists());
        $this->assertTrue(DB::table('sessions')->where('id', 'session-other')->exists());
    }
}
