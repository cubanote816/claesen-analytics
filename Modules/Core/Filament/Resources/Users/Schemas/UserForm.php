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
                        Select::make('fieldOpsClients')
                            ->label(__('users/resource.fields.clients'))
                            ->helperText(__('users/resource.fields.clients_hint'))
                            ->relationship('fieldOpsClients', 'name', fn ($query) => $query->orderBy('name'))
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ]),
            ]);
    }
}
