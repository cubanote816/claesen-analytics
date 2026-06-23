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

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public function form(Schema $schema): Schema
    {
        return CreateUserForm::configure($schema);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $employee = Employee::find($data['employee_id'] ?? null);

        abort_if(! $employee, 422, 'Empleado no encontrado.');
        abort_if(! $employee->fl_active, 422, 'Empleado inactivo.');
        abort_if(
            ! filter_var($employee->email ?? '', FILTER_VALIDATE_EMAIL),
            422, 'Email del empleado inválido o malformado.'
        );

        $domain = config('core.company_email_domain');
        abort_if(
            ! str_ends_with(strtolower(trim($employee->email)), '@' . $domain),
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
            'employee_id'     => $employee->id,
            'name'            => $employee->name,
            'email'           => $employee->email,
            'password'        => null,
            'password_set_at' => null,
            'role_ids'        => $data['role_ids'] ?? [],   // preserved for handleRecordCreation()
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $roleIds = $data['role_ids'] ?? [];
        unset($data['role_ids']);

        if (empty($roleIds)) {
            throw new DomainException('At least one role is required.');
        }

        return DB::transaction(function () use ($data, $roleIds): User {
            $user = User::create($data);
            $user->syncRoles($roleIds);     // failure here rolls back the User::create
            return $user;
        });
    }
}
