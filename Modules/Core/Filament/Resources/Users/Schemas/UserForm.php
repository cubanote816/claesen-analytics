<?php

namespace Modules\Core\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\FoClient;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('users/resource.fields.name'))
                            ->required(),
                        TextInput::make('email')
                            ->label(__('users/resource.fields.email'))
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),
                        // CLA-599: shown, never editable here — moving a user between
                        // organizations is a sensitive operation of its own (audit + step-up).
                        Placeholder::make('organization')
                            ->label(__('users/resource.fields.organization'))
                            ->content(fn (?User $record) => $record?->organization?->name ?? '—')
                            ->visible(fn (?User $record) => $record !== null),
                        Toggle::make('is_active')
                            ->label(__('users/resource.fields.is_active'))
                            ->helperText(__('users/resource.fields.is_active_hint'))
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger'),
                        // F2/CLA-464: roles are no longer editable through this
                        // general save — changing them requires a fresh MFA
                        // challenge, only available through the dedicated
                        // "Change roles" header action on the Edit page
                        // (Modules\Core\Filament\Resources\Users\Pages\EditUser).
                        // This is read-only, never dehydrated.
                        Placeholder::make('roles')
                            ->label(__('users/resource.fields.roles'))
                            ->content(fn ($record) => $record
                                ? $record->roles->pluck('name')->map(fn ($name) => Str::headline($name))->implode(', ')
                                : '—')
                            ->visible(fn (?User $record) => $record !== null),
                        // CLA-553: deliberately NOT ->relationship() — a plain relationship()
                        // sync() on save reattaches every fo_client_user row using the
                        // migration defaults (is_active/can_view=true, can_manage_contacts=
                        // false), silently wiping out any can_manage_contacts=true an admin
                        // had set earlier. client_ids + the 4 toggles below are synced
                        // manually in EditUser::handleRecordUpdate() instead.
                        Select::make('client_ids')
                            ->label(__('users/resource.fields.clients'))
                            ->helperText(__('users/resource.fields.clients_hint'))
                            ->options(fn (): array => FoClient::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->visible(fn (?User $record): bool => $record?->hasRole('client') ?? false),
                        Toggle::make('client_pivot_is_active')
                            ->label(__('users/resource.fields.client_pivot_is_active'))
                            ->helperText(__('users/resource.fields.client_pivot_is_active_hint'))
                            ->default(true)
                            ->visible(fn (?User $record): bool => $record?->hasRole('client') ?? false),
                        Toggle::make('client_can_view')
                            ->label(__('users/resource.fields.client_can_view'))
                            ->default(true)
                            ->visible(fn (?User $record): bool => $record?->hasRole('client') ?? false),
                        Toggle::make('client_can_report')
                            ->label(__('users/resource.fields.client_can_report'))
                            ->default(true)
                            ->visible(fn (?User $record): bool => $record?->hasRole('client') ?? false),
                        Toggle::make('client_can_manage_contacts')
                            ->label(__('users/resource.fields.client_can_manage_contacts'))
                            ->helperText(__('users/resource.fields.client_can_manage_contacts_hint'))
                            ->default(false)
                            ->visible(fn (?User $record): bool => $record?->hasRole('client') ?? false),
                    ]),
            ]);
    }
}
