<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Modules\Cafca\Models\Employee;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\FoClient;
use Spatie\Permission\Models\Role;

class CreateUserForm
{
    public static function configure(Schema $schema): Schema
    {
        $domain = config('core.company_email_domain');

        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('account_type')
                            ->label(__('users/resource.fields.account_type'))
                            ->options([
                                'internal' => __('users/resource.fields.internal_account'),
                                'client' => __('users/resource.fields.client_account'),
                            ])
                            ->default('internal')
                            ->required()
                            ->live(),

                        Select::make('employee_id')
                            ->label(__('users/resource.fields.employee'))
                            ->helperText(__('users/resource.fields.employee_hint', ['domain' => $domain]))
                            ->options(function () use ($domain): array {
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
                                    ->filter(fn (Employee $e) => filter_var($e->email ?? '', FILTER_VALIDATE_EMAIL) &&
                                        str_ends_with(strtolower(trim($e->email)), '@'.$domain) &&
                                        ! in_array(strtolower(trim($e->email)), $takenEmails, true) &&
                                        ! in_array($e->id, $takenEmployeeIds, true)
                                    )
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $email = $state ? (Employee::find($state)?->email ?? '') : '';
                                $set('email', $email);
                            })
                            ->required(fn (Get $get): bool => $get('account_type') !== 'client')
                            ->visible(fn (Get $get): bool => $get('account_type') !== 'client'),

                        TextInput::make('email')
                            ->label(__('users/resource.fields.email'))
                            ->email()
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder(__('users/resource.fields.email_placeholder'))
                            ->visible(fn (Get $get): bool => $get('account_type') !== 'client'),

                        TextInput::make('client_name')
                            ->label(__('users/resource.fields.name'))
                            ->required(fn (Get $get): bool => $get('account_type') === 'client')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('account_type') === 'client'),

                        TextInput::make('client_email')
                            ->label(__('users/resource.fields.email'))
                            ->email()
                            ->required(fn (Get $get): bool => $get('account_type') === 'client')
                            ->unique(User::class, 'email')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('account_type') === 'client'),

                        Select::make('client_ids')
                            ->label(__('users/resource.fields.clients'))
                            ->helperText(__('users/resource.fields.clients_hint'))
                            ->options(fn (): array => FoClient::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get): bool => $get('account_type') === 'client')
                            ->minItems(1)
                            ->visible(fn (Get $get): bool => $get('account_type') === 'client'),

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
                            ->required(fn (Get $get): bool => $get('account_type') !== 'client')
                            ->visible(fn (Get $get): bool => $get('account_type') !== 'client'),
                    ]),
            ]);
    }
}
