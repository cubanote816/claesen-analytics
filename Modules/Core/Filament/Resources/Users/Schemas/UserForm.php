<?php

namespace Modules\Core\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                        CheckboxList::make('roles')
                            ->label(__('users/resource.fields.roles'))
                            ->relationship(
                                'roles',
                                'name',
                                fn ($query) => $query->orderBy('sort')
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => \Illuminate\Support\Str::headline($record->name))
                            ->columns(2)
                            ->gridDirection('row'),
                    ]),
            ]);
    }
}
