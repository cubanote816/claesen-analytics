<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
    /** CLA-683: roles an external worker (not in Cafca) can never receive. */
    public const EXTERNAL_FORBIDDEN_ROLES = ['super_admin', 'client'];

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
                                'external' => __('users/resource.fields.external_account'),
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
                            ->required(fn (Get $get): bool => $get('account_type') === 'internal')
                            ->visible(fn (Get $get): bool => $get('account_type') === 'internal'),

                        TextInput::make('email')
                            ->label(__('users/resource.fields.email'))
                            ->email()
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder(__('users/resource.fields.email_placeholder'))
                            ->visible(fn (Get $get): bool => $get('account_type') === 'internal'),

                        // CLA-683: internal workers who are not in Cafca. They sign in with a
                        // password set through the one-time activation code (CLA-653).
                        TextInput::make('external_name')
                            ->label(__('users/resource.fields.name'))
                            ->required(fn (Get $get): bool => $get('account_type') === 'external')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('account_type') === 'external'),

                        TextInput::make('external_email')
                            ->label(__('users/resource.fields.email'))
                            ->helperText(__('users/resource.fields.external_email_hint', ['domain' => $domain]))
                            ->email()
                            ->required(fn (Get $get): bool => $get('account_type') === 'external')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('account_type') === 'external'),

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

                        // CLA-553: bootstrap point for the client-managed contacts feature —
                        // ClientContactInvitationService::assertCanManageContacts() already
                        // authorizes a client user with can_manage_contacts=true to invite more
                        // contacts for their own FoClient; before this field, no creation path
                        // in the codebase ever set that flag to true, so the capability was
                        // built but unreachable without a manual SQL edit.
                        Toggle::make('client_can_manage_contacts')
                            ->label(__('users/resource.fields.client_can_manage_contacts'))
                            ->helperText(__('users/resource.fields.client_can_manage_contacts_hint'))
                            ->default(false)
                            ->visible(fn (Get $get): bool => $get('account_type') === 'client'),

                        // role_ids: plain array, NOT using relationship() so that
                        // handleRecordCreation() can syncRoles() inside the DB transaction.
                        CheckboxList::make('role_ids')
                            ->label(__('users/resource.fields.roles'))
                            ->options(fn (Get $get): array => self::roleOptions((string) $get('account_type')))
                            ->columns(2)
                            ->gridDirection('row')
                            ->required(fn (Get $get): bool => $get('account_type') !== 'client')
                            ->visible(fn (Get $get): bool => $get('account_type') !== 'client'),
                    ]),
            ]);
    }

    /**
     * Role checkboxes offered for an account type: every role, except that an
     * external worker never sees Super Admin or Client (also refused server-side).
     *
     * @return array<int, string> role id => headline name
     */
    public static function roleOptions(string $accountType): array
    {
        return Role::orderBy('sort')
            ->when($accountType === 'external', fn ($query) => $query->whereNotIn('name', self::EXTERNAL_FORBIDDEN_ROLES))
            ->pluck('name', 'id')
            ->map(fn ($name) => \Illuminate\Support\Str::headline($name))
            ->toArray();
    }
}
