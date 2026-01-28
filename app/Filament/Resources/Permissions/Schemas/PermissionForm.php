<?php

namespace App\Filament\Resources\Permissions\Schemas;

use Filament\Schemas\Schema;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class PermissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('permissions/resource.fields.name'))
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('guard_name')
                            ->label(__('permissions/resource.fields.guard_name'))
                            ->default('web'),
                    ])
            ]);
    }
}
