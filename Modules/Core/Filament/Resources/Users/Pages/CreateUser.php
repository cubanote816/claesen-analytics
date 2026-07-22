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
                'role_ids' => [Role::findByName('client')->id],
            ];
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

    protected function handleRecordCreation(array $data): Model
    {
        $roleIds = $data['role_ids'] ?? [];
        $clientIds = $data['client_ids'] ?? [];
        unset($data['role_ids'], $data['client_ids'], $data['account_type']);

        if (empty($roleIds)) {
            throw new DomainException('At least one role is required.');
        }

        return DB::transaction(function () use ($data, $roleIds, $clientIds): User {
            $user = User::create($data);
            $user->syncRoles($roleIds);     // failure here rolls back the User::create

            if ($clientIds !== []) {
                $user->fieldOpsClients()->attach($clientIds, [
                    'is_active' => true,
                    'can_view' => true,
                    'can_report' => true,
                    'can_manage_contacts' => false,
                ]);
            }

            return $user;
        });
    }
}
