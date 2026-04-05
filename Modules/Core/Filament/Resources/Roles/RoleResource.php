<?php

namespace Modules\Core\Filament\Resources\Roles;

use Modules\Core\Filament\Resources\Roles\Pages\CreateRole;
use Modules\Core\Filament\Resources\Roles\Pages\EditRole;
use Modules\Core\Filament\Resources\Roles\Pages\ListRoles;
use Modules\Core\Filament\Resources\Roles\Schemas\RoleForm;
use Modules\Core\Filament\Resources\Roles\Tables\RolesTable;
use Modules\Core\Models\Role;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;
    
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('roles/resource.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('roles/resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('roles/resource.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
