<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Cafca\Models\Employee;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;

class CreateUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('employee_id')
                            ->label(__('users/resource.fields.employee'))
                            ->options(function (): array {
                                $takenEmails = User::pluck('email')
                                    ->map(fn ($e) => strtolower(trim($e)))
                                    ->filter()
                                    ->values()
                                    ->toArray();

                                $takenEmployeeIds = User::whereNotNull('employee_id')
                                    ->pluck('employee_id')
                                    ->toArray();

                                return Employee::where('fl_active', true)
                                    ->whereNotNull('email')
                                    ->where('email', '!=', '')
                                    ->get()
                                    ->filter(fn (Employee $e) =>
                                        filter_var($e->email ?? '', FILTER_VALIDATE_EMAIL) &&
                                        ! in_array(strtolower(trim($e->email)), $takenEmails, true) &&
                                        ! in_array($e->id, $takenEmployeeIds, true)
                                    )
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->required(),

                        // role_ids: plain array, NOT using relationship() so that
                        // handleRecordCreation() can syncRoles() inside the DB transaction.
                        CheckboxList::make('role_ids')
                            ->label(__('users/resource.fields.roles'))
                            ->options(fn () => Role::orderBy('sort')
                                ->pluck('name', 'id')
                                ->map(fn ($name) => \Illuminate\Support\Str::headline($name))
                                ->toArray())
                            ->columns(2)
                            ->gridDirection('row')
                            ->required(),
                    ]),
            ]);
    }
}
