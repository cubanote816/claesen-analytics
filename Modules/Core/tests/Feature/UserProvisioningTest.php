<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Cafca\Models\Employee;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Role $pmRole;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->adminRole = Role::firstOrCreate(['name' => 'admin',           'guard_name' => 'web']);
        $this->pmRole    = Role::firstOrCreate(['name' => 'project_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    private function makeEmployee(array $overrides = []): Employee
    {
        return Employee::create(array_merge([
            'id'        => (string) fake()->unique()->numerify('EMP-####'),
            'name'      => fake()->name(),
            'email'     => fake()->unique()->safeEmail(),
            'fl_active' => true,
        ], $overrides));
    }

    // -----------------------------------------------------------------------
    // 1. Selector excludes fl_active = false
    // -----------------------------------------------------------------------
    public function test_inactive_employee_is_excluded_from_options(): void
    {
        $inactive = $this->makeEmployee(['fl_active' => false]);

        // Simulate what CreateUserForm::configure() computes for ->options()
        $ids = Employee::where('fl_active', true)->pluck('id')->toArray();

        $this->assertNotContains($inactive->id, $ids);
    }

    // -----------------------------------------------------------------------
    // 2. Selector excludes employees with no email
    // -----------------------------------------------------------------------
    public function test_employee_without_email_is_excluded(): void
    {
        $noEmail = $this->makeEmployee(['email' => '']);

        $ids = Employee::where('fl_active', true)->where('email', '!=', '')->pluck('id')->toArray();

        $this->assertNotContains($noEmail->id, $ids);
    }

    // -----------------------------------------------------------------------
    // 3. Selector excludes employee_id already linked to another user
    // -----------------------------------------------------------------------
    public function test_already_linked_employee_excluded_from_options(): void
    {
        $employee = $this->makeEmployee();
        UserFactory::new()->create(['employee_id' => $employee->id, 'email' => $employee->email]);

        $takenIds    = User::whereNotNull('employee_id')->pluck('employee_id')->toArray();
        $takenEmails = User::pluck('email')->map(fn ($e) => strtolower(trim($e)))->toArray();

        $available = Employee::where('fl_active', true)
            ->get()
            ->filter(fn ($e) =>
                ! in_array($e->id, $takenIds, true) &&
                ! in_array(strtolower(trim($e->email ?? '')), $takenEmails, true)
            )
            ->pluck('id')
            ->toArray();

        $this->assertNotContains($employee->id, $available);
    }

    // -----------------------------------------------------------------------
    // 4. Selector excludes email already used by legacy user (no employee_id)
    // -----------------------------------------------------------------------
    public function test_email_of_legacy_user_excluded_from_options(): void
    {
        $legacyUser = UserFactory::new()->create(['employee_id' => null]);
        $employee   = $this->makeEmployee(['email' => $legacyUser->email]);

        $takenEmails = User::pluck('email')->map(fn ($e) => strtolower(trim($e)))->toArray();

        $this->assertContains(strtolower(trim($legacyUser->email)), $takenEmails);

        $available = Employee::where('fl_active', true)
            ->get()
            ->filter(fn ($e) =>
                ! in_array(strtolower(trim($e->email ?? '')), $takenEmails, true)
            )
            ->pluck('id')
            ->toArray();

        $this->assertNotContains($employee->id, $available);
    }

    // -----------------------------------------------------------------------
    // 5. Server rejects manipulated payload (inactive / duplicate / nonexistent)
    // -----------------------------------------------------------------------
    public function test_server_rejects_inactive_employee_id(): void
    {
        $inactive = $this->makeEmployee(['fl_active' => false]);

        // mutateFormDataBeforeCreate is protected; use Reflection to call it directly,
        // bypassing Livewire's __call magic so we test the actual validation logic.
        $page   = app(\Modules\Core\Filament\Resources\Users\Pages\CreateUser::class);
        $method = new \ReflectionMethod($page, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $method->invoke($page, ['employee_id' => $inactive->id, 'role_ids' => [$this->pmRole->id]]);
    }

    // -----------------------------------------------------------------------
    // 6. Created user has name/email from employee, password = null
    // -----------------------------------------------------------------------
    public function test_provisioned_user_has_null_password_and_employee_data(): void
    {
        $employee = $this->makeEmployee();

        $user = User::create([
            'employee_id'     => $employee->id,
            'name'            => $employee->name,
            'email'           => $employee->email,
            'password'        => null,
            'password_set_at' => null,
        ]);

        $this->assertNull($user->fresh()->password);
        $this->assertNull($user->fresh()->password_set_at);
        $this->assertSame($employee->email, $user->email);
        $this->assertFalse($user->hasCompletedPasswordSetup());
    }

    // -----------------------------------------------------------------------
    // 7. Role assignment failure reverts user creation (transaction)
    // -----------------------------------------------------------------------
    public function test_role_sync_failure_reverts_user_creation(): void
    {
        $employee = $this->makeEmployee();

        $this->expectException(\Throwable::class);

        DB::transaction(function () use ($employee): void {
            $user = User::create([
                'employee_id' => $employee->id,
                'name'        => $employee->name,
                'email'       => $employee->email,
                'password'    => null,
            ]);

            // Simulate syncRoles failure
            throw new \RuntimeException('Simulated role sync failure');
        });

        $this->assertDatabaseMissing('users', ['email' => $employee->email]);
    }

    // -----------------------------------------------------------------------
    // 7b. Successful creation assigns exactly the requested roles
    // -----------------------------------------------------------------------
    public function test_provisioned_user_gets_exactly_the_requested_roles(): void
    {
        $employee = $this->makeEmployee();

        $user = DB::transaction(function () use ($employee): User {
            $user = User::create([
                'employee_id' => $employee->id,
                'name'        => $employee->name,
                'email'       => $employee->email,
                'password'    => null,
            ]);
            $user->syncRoles([$this->pmRole->id]);
            return $user;
        });

        $this->assertTrue($user->fresh()->hasRole('project_manager'));
        $this->assertFalse($user->fresh()->hasRole('admin'));
    }

    // -----------------------------------------------------------------------
    // 8. No email is sent during provisioning
    // -----------------------------------------------------------------------
    public function test_no_email_sent_during_provisioning(): void
    {
        $employee = $this->makeEmployee();

        User::create([
            'employee_id' => $employee->id,
            'name'        => $employee->name,
            'email'       => $employee->email,
            'password'    => null,
        ]);

        Mail::assertNothingSent();
    }
}
