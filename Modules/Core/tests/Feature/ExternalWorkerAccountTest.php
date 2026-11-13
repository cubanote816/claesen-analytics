<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Database\Factories\UserFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Modules\Cafca\Models\Employee;
use Modules\Core\Filament\Resources\Users\Pages\CreateUser;
use Modules\Core\Filament\Resources\Users\Schemas\CreateUserForm;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Core\Services\PasswordResetService;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * CLA-683: internal workers who are not in Cafca get a backoffice account of type
 * "external": free name + email, no employee record, never Super Admin or Client,
 * never a corporate address (those keep the Cafca employee + Azure path).
 */
class ExternalWorkerAccountTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $viewerRole;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->viewerRole = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    private function mutate(array $data): array
    {
        $page = app(CreateUser::class);
        $method = new \ReflectionMethod($page, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        return $method->invoke($page, $data);
    }

    private function create(array $data): User
    {
        $page = app(CreateUser::class);
        $method = new \ReflectionMethod($page, 'handleRecordCreation');
        $method->setAccessible(true);

        return $method->invoke($page, $this->mutate($data));
    }

    private function external(array $overrides = []): array
    {
        return array_merge([
            'account_type' => 'external',
            'external_name' => '  Jan Freelance  ',
            'external_email' => '  Jan.Freelance@Gmail.com ',
            'role_ids' => [$this->adminRole->id, $this->viewerRole->id],
        ], $overrides);
    }

    /**
     * Refusals surface as a field error on the form (a 422 ValidationException),
     * not as an error page, and create nothing.
     */
    private function assertRefused(array $data, string $reason, string $field = 'external_email'): void
    {
        $before = User::count();

        try {
            $this->mutate($data);
            $this->fail('The external worker account was not refused.');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame([$reason], $e->errors()['data.'.$field] ?? null, json_encode($e->errors()));
        }

        $this->assertSame($before, User::count());
    }

    public function test_it_creates_an_external_worker_with_no_employee_and_no_password(): void
    {
        $user = $this->create($this->external());

        $this->assertSame('Jan Freelance', $user->name);
        $this->assertSame('jan.freelance@gmail.com', $user->email);
        $this->assertNull($user->employee_id);
        $this->assertNull($user->password);
        $this->assertNull($user->password_set_at);
        $this->assertNull($user->microsoft_id);
        $this->assertFalse($user->hasCompletedPasswordSetup());
        $this->assertSame(Organization::claesenId(), $user->fresh()->organization_id);
        $this->assertEqualsCanonicalizing(['admin', 'viewer'], $user->getRoleNames()->all());
        $this->assertTrue($user->hasPanelAccess());
    }

    public function test_it_refuses_a_corporate_address(): void
    {
        $this->assertRefused($this->external(['external_email' => 'Someone@Claesen-Verlichting.be']), 'Use the Internal employee type for @claesen-verlichting.be addresses.');
    }

    public function test_it_refuses_an_address_already_in_use(): void
    {
        UserFactory::new()->create(['email' => 'jan.freelance@gmail.com']);

        $this->assertRefused($this->external(), 'A user with this email already exists.');
    }

    public function test_it_refuses_an_invalid_address(): void
    {
        $this->assertRefused($this->external(['external_email' => 'not-an-email']), 'Invalid email address.');
    }

    public function test_it_refuses_a_missing_name(): void
    {
        $this->assertRefused($this->external(['external_name' => '   ']), 'A name is required for an external worker account.', 'external_name');
    }

    public static function forbiddenRoles(): array
    {
        return [
            'super admin' => ['super_admin'],
            'client' => ['client'],
        ];
    }

    #[DataProvider('forbiddenRoles')]
    public function test_it_refuses_a_forbidden_role(string $role): void
    {
        $this->assertRefused($this->external([
            'role_ids' => [$this->viewerRole->id, Role::findByName($role)->id],
        ]), 'External worker accounts cannot have the Super Admin or Client role.', 'role_ids');
    }

    public function test_it_refuses_no_role(): void
    {
        $this->assertRefused($this->external(['role_ids' => []]), 'At least one role is required.', 'role_ids');
    }

    public function test_the_form_does_not_offer_super_admin_or_client_to_external_workers(): void
    {
        $external = CreateUserForm::roleOptions('external');
        $internal = CreateUserForm::roleOptions('internal');

        $this->assertNotContains('Super Admin', $external);
        $this->assertNotContains('Client', $external);
        $this->assertContains('Admin', $external);
        $this->assertContains('Viewer', $external);
        $this->assertContains('Super Admin', $internal);
    }

    public function test_an_admin_creates_an_external_worker_from_the_users_page(): void
    {
        $admin = UserFactory::new()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'account_type' => 'external',
                'external_name' => 'Jan Freelance',
                'external_email' => 'jan.freelance@gmail.com',
                'role_ids' => [$this->viewerRole->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'jan.freelance@gmail.com')->firstOrFail();
        $this->assertNull($user->employee_id);
        $this->assertSame(['viewer'], $user->getRoleNames()->all());
    }

    // CLA-683 verify: forged protected attributes never reach the account, and the
    // created account can really activate itself with the CLA-653 code.
    public function test_forged_attributes_are_discarded_and_the_account_can_activate(): void
    {
        $user = $this->create($this->external([
            'employee_id' => 'forged', 'organization_id' => -1,
            'microsoft_id' => 'forged', 'password' => 'forged',
            'password_set_at' => now(), 'client_ids' => [999999],
            'client_can_manage_contacts' => true,
        ]))->refresh();

        foreach (['employee_id', 'microsoft_id', 'password', 'password_set_at'] as $key) {
            $this->assertNull($user->{$key}, $key);
        }
        $this->assertSame(Organization::claesenId(), $user->organization_id);
        $this->assertSame(0, $user->fieldOpsClients()->count());
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));

        $this->assertSame(0, Artisan::call('core:issue-activation-code', ['email' => $user->email]));
        $this->assertSame(1, preg_match('/^Code: ([A-Za-z0-9]{64})$/m', Artisan::output(), $match));
        app(PasswordResetService::class)->resetPassword($match[1], 'NewSecret123!');

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecret123!', $user->password));
        $this->assertTrue($user->hasCompletedPasswordSetup());
        $this->assertNull($user->activation_code_hash);
    }

    // CLA-683 verify: crafted form payloads (unknown account type, unknown or forbidden
    // roles) create nothing, whichever layer refuses them.
    public function test_crafted_form_payloads_create_nothing(): void
    {
        $admin = UserFactory::new()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $payloads = [
            ['account_type' => 'other'],
            ['role_ids' => [999999]],
            ['role_ids' => [$this->viewerRole->id.'oops']],
            ['role_ids' => [Role::findByName('super_admin')->id]],
            ['role_ids' => [Role::findByName('client')->id]],
        ];

        foreach ($payloads as $payload) {
            $before = User::orderBy('id')->get()->map->getRawOriginal()->all();

            try {
                Livewire::test(CreateUser::class)
                    ->fillForm(array_merge($this->external(), $payload))
                    ->call('create')
                    ->assertHasFormErrors();
            } catch (HttpException $e) {
                $this->assertSame(422, $e->getStatusCode());
            }

            $this->assertSame($before, User::orderBy('id')->get()->map->getRawOriginal()->all(), json_encode($payload));
        }
    }

    // CLA-683 S5: the default (Internal employee) flow still creates from a Cafca employee.
    public function test_the_default_internal_flow_still_creates_from_a_cafca_employee(): void
    {
        $admin = UserFactory::new()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $employee = Employee::create([
            'id' => 'EMP-6830', 'name' => 'Inge Intern',
            'email' => 'inge.intern@claesen-verlichting.be', 'fl_active' => true,
        ]);

        Livewire::test(CreateUser::class)
            ->fillForm(['employee_id' => $employee->id, 'role_ids' => [$this->viewerRole->id]])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('employee_id', 'EMP-6830')->firstOrFail();
        $this->assertSame('inge.intern@claesen-verlichting.be', $user->email);
        $this->assertSame(['viewer'], $user->getRoleNames()->all());
    }

    // CLA-683 S5: the default form is still Internal employee and still requires an employee.
    public function test_the_default_internal_form_still_requires_an_employee(): void
    {
        $admin = UserFactory::new()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $before = User::count();

        Livewire::test(CreateUser::class)
            ->assertFormSet(['account_type' => 'internal'])
            ->fillForm(['role_ids' => [$this->viewerRole->id]])
            ->call('create')
            ->assertHasFormErrors(['employee_id' => 'required']);

        $this->assertSame($before, User::count());
    }

    // CLA-683 local test: a corporate or duplicate address showed an error page;
    // it must show as a validation error on the email field instead.
    public static function rejectedEmails(): array
    {
        return [
            'corporate' => ['someone@claesen-verlichting.be', 'Use the Internal employee type for @claesen-verlichting.be addresses.'],
            'duplicate' => ['taken@gmail.com', 'A user with this email already exists.'],
        ];
    }

    #[DataProvider('rejectedEmails')]
    public function test_a_rejected_email_shows_as_a_form_error(string $email, string $message): void
    {
        UserFactory::new()->create(['email' => 'taken@gmail.com']);
        $admin = UserFactory::new()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $before = User::count();

        Livewire::test(CreateUser::class)
            ->fillForm($this->external(['external_email' => $email]))
            ->call('create')
            ->assertHasFormErrors(['external_email'])
            ->assertSee($message);

        $this->assertSame($before, User::count());
    }
}
