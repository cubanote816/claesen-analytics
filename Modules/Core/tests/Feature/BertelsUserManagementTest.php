<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Filament\Bertels\Resources\Users\BertelsUserResource;
use App\Filament\Bertels\Resources\Users\Pages\CreateBertelsUser;
use App\Filament\Bertels\Resources\Users\Pages\EditBertelsUser;
use App\Filament\Bertels\Resources\Users\Pages\ListBertelsUsers;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\Users\Pages\CreateUser;
use Modules\Core\Filament\Resources\Users\Schemas\CreateUserForm;
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

        foreach (['super_admin', 'admin', 'viewer', 'client', 'technician', 'finance'] as $role) {
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
        return ['super_admin' => ['super_admin'], 'client' => ['client']];
    }

    // S10 (supersedes S4 viewer/admin): every role except super_admin and client is accepted.
    public function test_every_other_role_is_accepted_on_create(): void
    {
        Livewire::test(CreateBertelsUser::class)
            ->fillForm($this->localForm(['role_ids' => [$this->roleId('viewer'), $this->roleId('finance'), $this->roleId('technician')]]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(
            ['finance', 'technician', 'viewer'],
            User::where('email', 'luc@gmail.com')->firstOrFail()->getRoleNames()->all(),
        );
    }

    // S10
    #[DataProvider('forbiddenRoles')]
    public function test_super_admin_and_client_are_rejected_on_create_and_nothing_is_stored(string $role): void
    {
        $before = User::count();

        Livewire::test(CreateBertelsUser::class)
            ->fillForm($this->localForm(['role_ids' => [$this->roleId('viewer'), $this->roleId($role)]]))
            ->call('create')
            ->assertHasFormErrors();

        $this->assertSame($before, User::count());
        Notification::assertNothingSent();
    }

    // S10: the server-side rule holds even when the form's own option check is bypassed.
    #[DataProvider('forbiddenRoles')]
    public function test_the_create_page_itself_refuses_super_admin_and_client(string $role): void
    {
        $page = app(CreateBertelsUser::class);
        $method = new \ReflectionMethod($page, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        try {
            $method->invoke($page, $this->localForm(['role_ids' => [$this->roleId($role)]]));
            $this->fail('A forbidden role was not refused.');
        } catch (ValidationException $e) {
            $this->assertSame(
                ['Bertels accounts cannot have the Super Admin or Client role.'],
                $e->errors()['data.role_ids'] ?? null,
            );
        }
    }

    // S10: same list, same order as Claesen's External worker checkboxes.
    public function test_the_role_options_are_claesens_external_worker_list(): void
    {
        $expected = CreateUserForm::roleOptions('external');

        $this->assertSame($expected, BertelsUserResource::assignableRoleOptions());
        $this->assertEqualsCanonicalizing(
            ['admin', 'finance', 'technician', 'viewer'],
            array_map('strtolower', array_values($expected)),
        );
    }

    // S10
    public function test_change_roles_accepts_any_role_but_super_admin_and_client(): void
    {
        $target = $this->bertelsUser();

        Livewire::test(EditBertelsUser::class, ['record' => $target->getKey()])
            ->callAction('changeRoles', data: ['code' => $this->stepUpCode(), 'roles' => [$this->roleId('finance'), $this->roleId('technician')]])
            ->assertHasNoActionErrors();

        $this->assertEqualsCanonicalizing(['finance', 'technician'], $target->fresh()->roles->pluck('name')->all());
    }

    // S10
    #[DataProvider('forbiddenRoles')]
    public function test_change_roles_rejects_super_admin_and_client_and_stores_nothing(string $role): void
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

    // ------------------------------------------------------------------ S10

    // S10: navigation group declared with the same label as Claesen's panel. The group icon
    // cannot be copied: this panel's sidebar is not collapsible, and Filament rejects a group
    // icon together with item icons there (the Users item keeps its icon).
    public function test_the_user_management_group_is_declared_with_claesens_label(): void
    {
        $find = fn (string $panel) => collect(Filament::getPanel($panel)->getNavigationGroups())
            ->first(fn ($group) => $group->getLabel() === __('navigation.groups.user_management'));

        $claesen = $find('admin');
        $bertels = $find('bertels');

        $this->assertNotNull($claesen);
        $this->assertNotNull($bertels);
        $this->assertNull($bertels->getIcon());
        $this->assertNotNull(BertelsUserResource::getNavigationIcon());
        $this->get('/bertels/users')->assertSuccessful();
        $this->assertSame(BertelsUserResource::getNavigationGroup(), $bertels->getLabel());
    }

    // S10
    public function test_the_list_shows_claesens_columns_in_claesens_order(): void
    {
        $this->bertelsUser();
        $test = Livewire::test(ListBertelsUsers::class);

        $columns = array_keys($test->instance()->getTable()->getColumns());
        $this->assertSame(
            ['name', 'organization.name', 'status', 'last_active_at', 'last_login_at', 'last_login_app_source', 'last_login_channel', 'email', 'roles.name', 'created_at'],
            $columns,
        );
        $this->assertTrue($test->instance()->getTable()->getColumn('created_at')->isToggledHiddenByDefault());
    }

    // S10
    public function test_the_organization_column_shows_bertels_and_the_status_badge_is_offline(): void
    {
        $user = $this->bertelsUser();

        Livewire::test(ListBertelsUsers::class)
            ->assertTableColumnStateSet('organization.name', 'Electro Bertels', $user)
            ->assertTableColumnStateSet('status', __('users/resource.fields.offline'), $user)
            ->assertTableColumnStateSet('last_login_channel', __('users/resource.fields.unknown_channel'), $user);
    }

    // S10
    public function test_search_by_name_and_email(): void
    {
        $ana = $this->bertelsUser(['name' => 'Ana Zeppelin', 'email' => 'ana@example.com']);
        $bob = $this->bertelsUser(['name' => 'Bob Quartz', 'email' => 'bob@example.org']);

        Livewire::test(ListBertelsUsers::class)
            ->searchTable('Zeppelin')
            ->assertCanSeeTableRecords([$ana])
            ->assertCanNotSeeTableRecords([$bob])
            ->searchTable('bob@example.org')
            ->assertCanSeeTableRecords([$bob])
            ->assertCanNotSeeTableRecords([$ana]);
    }

    // S10
    public function test_sorting_by_name_and_email(): void
    {
        $a = $this->bertelsUser(['name' => 'Aaa', 'email' => 'zzz@example.com']);
        $b = $this->bertelsUser(['name' => 'Bbb', 'email' => 'aaa@example.com']);

        Livewire::test(ListBertelsUsers::class)
            ->sortTable('name')
            ->assertCanSeeTableRecords([$a, $b], inOrder: true)
            ->sortTable('name', 'desc')
            ->assertCanSeeTableRecords([$b, $a], inOrder: true)
            ->sortTable('email')
            ->assertCanSeeTableRecords([$b, $a], inOrder: true);
    }

    // S10: one organization → no organization filter.
    public function test_there_is_no_organization_filter(): void
    {
        $this->assertSame([], Livewire::test(ListBertelsUsers::class)->instance()->getTable()->getFilters());
    }

    // S10
    public function test_delete_on_the_edit_page_removes_only_that_bertels_user(): void
    {
        $target = $this->bertelsUser();
        $other = $this->bertelsUser();

        Livewire::test(EditBertelsUser::class, ['record' => $target->getKey()])
            ->callAction('delete');

        $this->assertNull(User::find($target->id));
        $this->assertNotNull(User::find($other->id));
    }

    // S10
    public function test_bulk_delete_removes_the_selected_bertels_users(): void
    {
        $one = $this->bertelsUser();
        $two = $this->bertelsUser();
        $keep = $this->bertelsUser();

        Livewire::test(ListBertelsUsers::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertNull(User::find($one->id));
        $this->assertNull(User::find($two->id));
        $this->assertNotNull(User::find($keep->id));
    }

    // S10 (security): a Claesen id posted into a bulk delete never deletes a Claesen user.
    public function test_bulk_delete_never_deletes_a_claesen_user(): void
    {
        $bertelsUser = $this->bertelsUser();
        $claesenUser = $this->claesenUser();

        try {
            Livewire::test(ListBertelsUsers::class)
                ->set('selectedTableRecords', [(string) $bertelsUser->getKey(), (string) $claesenUser->getKey()])
                ->callAction(TestAction::make('delete')->table()->bulk());
        } catch (\Throwable) {
            // a refusal is acceptable; what matters is what survives
        }

        $this->assertNotNull(User::find($claesenUser->id));
        $this->assertNotNull(User::find($this->superAdmin->id));
    }

    // S10 (security): deleting a Claesen user through the Bertels edit page is a 404.
    public function test_a_claesen_user_cannot_be_deleted_through_the_bertels_edit_page(): void
    {
        $claesenUser = $this->claesenUser();

        Livewire::test(EditBertelsUser::class, ['record' => $claesenUser->getKey()])->assertNotFound();

        $this->assertNotNull(User::find($claesenUser->id));
    }

    public static function nonSuperAdminRoles(): array
    {
        return ['bertels admin' => ['admin'], 'bertels viewer' => ['viewer']];
    }

    // S10 / S1
    #[DataProvider('nonSuperAdminRoles')]
    public function test_non_super_admin_cannot_delete(string $role): void
    {
        $actor = $this->bertelsUser([], $role);
        $target = $this->bertelsUser();

        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('bertels'));

        $this->assertFalse(BertelsUserResource::canDelete($target));
        $this->assertFalse(BertelsUserResource::canDeleteAny());
        $this->get("/bertels/users/{$target->getKey()}/edit")->assertForbidden();
        $this->assertNotNull(User::find($target->id));
    }

    // S10
    public function test_super_admin_can_delete(): void
    {
        $this->assertTrue(BertelsUserResource::canDelete($this->bertelsUser()));
        $this->assertTrue(BertelsUserResource::canDeleteAny());
    }

    // S10
    public function test_email_edit_within_the_account_type_is_saved(): void
    {
        $local = $this->bertelsUser(['email' => 'old@gmail.com']);
        $microsoft = $this->bertelsUser(['email' => 'old@electrobertels.be']);

        Livewire::test(EditBertelsUser::class, ['record' => $local->getKey()])
            ->fillForm(['email' => 'new@example.com'])
            ->call('save')
            ->assertHasNoFormErrors();
        Livewire::test(EditBertelsUser::class, ['record' => $microsoft->getKey()])
            ->fillForm(['email' => 'new@electrobertels.be'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('new@example.com', $local->fresh()->email);
        $this->assertSame('new@electrobertels.be', $microsoft->fresh()->email);
    }

    public static function crossings(): array
    {
        return [
            'local to microsoft' => ['old@gmail.com', 'old@electrobertels.be'],
            'microsoft to local' => ['old@electrobertels.be', 'old@gmail.com'],
            'microsoft to local, case/space' => ['old@electrobertels.be', 'Old@Other.com'],
        ];
    }

    // S10
    #[DataProvider('crossings')]
    public function test_an_email_change_crossing_the_bertels_domain_is_rejected_and_nothing_is_stored(string $from, string $to): void
    {
        $user = $this->bertelsUser(['email' => $from, 'name' => 'Before']);

        Livewire::test(EditBertelsUser::class, ['record' => $user->getKey()])
            ->fillForm(['email' => $to, 'name' => 'After'])
            ->call('save')
            ->assertHasFormErrors(['email']);

        $fresh = $user->fresh();
        $this->assertSame($from, $fresh->email);
        $this->assertSame('Before', $fresh->name);
    }

    // S10
    public function test_a_duplicate_email_is_rejected_on_edit(): void
    {
        $this->claesenUser(['email' => 'taken@gmail.com']);
        $user = $this->bertelsUser(['email' => 'mine@gmail.com']);

        Livewire::test(EditBertelsUser::class, ['record' => $user->getKey()])
            ->fillForm(['email' => 'taken@gmail.com'])
            ->call('save')
            ->assertHasFormErrors(['email']);

        $this->assertSame('mine@gmail.com', $user->fresh()->email);
    }

    // S10: unchanged email on save is not a "duplicate of itself".
    public function test_saving_without_changing_the_email_works(): void
    {
        $user = $this->bertelsUser(['email' => 'same@gmail.com']);

        Livewire::test(EditBertelsUser::class, ['record' => $user->getKey()])
            ->fillForm(['name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed', $user->fresh()->name);
    }
}
