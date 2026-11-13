<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users\Pages;

use DomainException;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Cafca\Models\Employee;
use Modules\Core\Filament\Resources\Users\Schemas\CreateUserForm;
use Modules\Core\Filament\Resources\Users\UserResource;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\FoClient;
use Spatie\Permission\Models\Role;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public function form(Schema $schema): Schema
    {
        return CreateUserForm::configure($schema);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['account_type'] ?? 'internal') === 'client') {
            $clientIds = collect($data['client_ids'] ?? [])->filter()->unique()->values()->all();
            abort_if($clientIds === [], 422, 'At least one client is required for a client account.');
            abort_if(FoClient::query()->whereKey($clientIds)->count() !== count($clientIds), 422, 'One or more selected clients do not exist.');

            $name = trim((string) ($data['client_name'] ?? ''));
            abort_if($name === '', 422, 'A name is required for a client account.');

            $normalizedEmail = strtolower(trim((string) ($data['client_email'] ?? '')));
            abort_if(! filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL), 422, 'Invalid client email address.');
            abort_if(User::whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])->exists(), 422, 'A user with this email already exists.');

            return [
                'employee_id' => null,
                'name' => $name,
                'email' => $normalizedEmail,
                'password' => null,
                'password_set_at' => null,
                'client_ids' => $clientIds,
                'client_can_manage_contacts' => (bool) ($data['client_can_manage_contacts'] ?? false),
                'role_ids' => [Role::findByName('client')->id],
            ];
        }

        if (($data['account_type'] ?? 'internal') === 'external') {
            return $this->externalWorkerData($data);
        }

        $employee = Employee::find($data['employee_id'] ?? null);

        abort_if(! $employee, 422, 'Empleado no encontrado.');
        abort_if(! $employee->fl_active, 422, 'Empleado inactivo.');
        abort_if(
            ! filter_var($employee->email ?? '', FILTER_VALIDATE_EMAIL),
            422, 'Email del empleado inválido o malformado.'
        );

        $domain = config('core.company_email_domain');
        abort_if(
            ! str_ends_with(strtolower(trim($employee->email)), '@'.$domain),
            422, "Only @{$domain} email addresses are allowed for backoffice accounts."
        );

        $normalizedEmail = strtolower(trim($employee->email));

        abort_if(
            User::whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])->exists(),
            422, 'Ya existe un usuario con ese email.'
        );
        abort_if(
            User::where('employee_id', $employee->id)->exists(),
            422, 'Este empleado ya está vinculado a otro usuario.'
        );

        return [
            'employee_id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'password' => null,
            'password_set_at' => null,
            'role_ids' => $data['role_ids'] ?? [],   // preserved for handleRecordCreation()
        ];
    }

    /**
     * CLA-683: an internal worker who is not in Cafca. No employee record and no
     * password: they set one with the one-time activation code (CLA-653). Corporate
     * addresses keep the Cafca employee + Azure path, and Super Admin / Client are
     * never granted here.
     */
    private function externalWorkerData(array $data): array
    {
        $name = trim((string) ($data['external_name'] ?? ''));
        abort_if($name === '', 422, 'A name is required for an external worker account.');

        $normalizedEmail = strtolower(trim((string) ($data['external_email'] ?? '')));
        abort_if(! filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL), 422, 'Invalid email address.');

        $domain = config('core.company_email_domain');
        abort_if(
            str_ends_with($normalizedEmail, '@'.$domain),
            422, "Use the Internal employee type for @{$domain} addresses."
        );
        abort_if(
            User::whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])->exists(),
            422, 'A user with this email already exists.'
        );

        $roleIds = collect($data['role_ids'] ?? [])->filter()->unique()->values()->all();
        abort_if($roleIds === [], 422, 'At least one role is required.');
        abort_if(
            Role::query()->whereKey($roleIds)->count() !== count($roleIds),
            422, 'One or more selected roles do not exist.'
        );
        abort_if(
            Role::query()->whereKey($roleIds)->whereIn('name', CreateUserForm::EXTERNAL_FORBIDDEN_ROLES)->exists(),
            422, 'External worker accounts cannot have the Super Admin or Client role.'
        );

        return [
            'employee_id' => null,
            'name' => $name,
            'email' => $normalizedEmail,
            'password' => null,
            'password_set_at' => null,
            'role_ids' => $roleIds,
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $roleIds = $data['role_ids'] ?? [];
        $clientIds = $data['client_ids'] ?? [];
        $canManageContacts = (bool) ($data['client_can_manage_contacts'] ?? false);
        unset($data['role_ids'], $data['client_ids'], $data['account_type'], $data['client_can_manage_contacts']);

        if (empty($roleIds)) {
            throw new DomainException('At least one role is required.');
        }

        return DB::transaction(function () use ($data, $roleIds, $clientIds, $canManageContacts): User {
            // F1/P2 (docs/ai/adr-multi-organization.md): no organization picker
            // exists yet (lands in phase P6) — every backoffice user created
            // here belongs to Claesen until then.
            $data['organization_id'] = Organization::claesenId();

            $user = User::create($data);
            $user->syncRoles($roleIds);     // failure here rolls back the User::create

            if ($clientIds !== []) {
                $user->fieldOpsClients()->attach($clientIds, [
                    'is_active' => true,
                    'can_view' => true,
                    'can_report' => true,
                    'can_manage_contacts' => $canManageContacts,
                ]);
            }

            return $user;
        });
    }
}
