<?php

declare(strict_types=1);

namespace App\Filament\Bertels\Resources\Users\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Modules\Core\Models\User;

class BertelsUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('users/resource.fields.name'))
                            ->required()
                            ->maxLength(255),
                        // Never editable: the address decides the account type (Microsoft vs
                        // local), which is validated at creation only.
                        TextInput::make('email')
                            ->label(__('users/resource.fields.email'))
                            ->disabled()
                            ->dehydrated(false),
                        Placeholder::make('organization')
                            ->label(__('users/resource.fields.organization'))
                            ->content(fn (?User $record) => $record?->organization?->name ?? '—'),
                        Toggle::make('is_active')
                            ->label(__('users/resource.fields.is_active'))
                            ->helperText(__('users/resource.fields.is_active_hint'))
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger'),
                        // Roles change only through the step-up "Change roles" header action.
                        Placeholder::make('roles')
                            ->label(__('users/resource.fields.roles'))
                            ->content(fn (?User $record) => $record
                                ? $record->roles->pluck('name')->map(fn ($name) => Str::headline($name))->implode(', ')
                                : '—'),
                    ]),
            ]);
    }
}
