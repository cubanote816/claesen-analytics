<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->formatStateUsing(fn($state) => \Illuminate\Support\Str::headline($state))
                            ->dehydrateStateUsing(fn($state) => \Illuminate\Support\Str::snake($state)),
                        TextInput::make('guard_name')
                            ->default('web')
                            ->hidden(),
                    ]),
                Section::make('Permissions')
                    ->description('Manage user permissions')
                    ->schema(function () {
                        $permissions = \App\Models\Permission::all();

                        return $permissions
                            ->groupBy(fn($permission) => \Illuminate\Support\Str::before($permission->name, '_'))
                            ->map(function ($permissions, $group) {
                                return Section::make(\Illuminate\Support\Str::headline($group))
                                    ->schema([
                                        CheckboxList::make('permissions_' . $group)
                                            ->label('')
                                            ->options($permissions->pluck('name', 'id'))
                                            ->bulkToggleable()
                                            ->columns(2)
                                            ->gridDirection('row')
                                            ->afterStateHydrated(function (CheckboxList $component, $record) use ($permissions) {
                                                if (! $record) return;
                                                $component->state(
                                                    $record->permissions->pluck('id')
                                                        ->intersect($permissions->pluck('id'))
                                                        ->toArray()
                                                );
                                            })
                                            ->dehydrated(true)
                                    ]);
                            })->values()->toArray();
                    }),
            ]);
    }
}
