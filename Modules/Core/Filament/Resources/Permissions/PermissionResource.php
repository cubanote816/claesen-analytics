<?php

namespace Modules\Core\Filament\Resources\Permissions;

use Modules\Core\Filament\Resources\Permissions\Pages\CreatePermission;
use Modules\Core\Filament\Resources\Permissions\Pages\EditPermission;
use Modules\Core\Filament\Resources\Permissions\Pages\ListPermissions;
use Modules\Core\Filament\Resources\Permissions\Schemas\PermissionForm;
use Modules\Core\Filament\Resources\Permissions\Tables\PermissionsTable;
use Modules\Core\Models\Permission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;
    
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('permissions/resource.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('permissions/resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('permissions/resource.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return PermissionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PermissionsTable::configure($table);
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
            'index' => ListPermissions::route('/'),
            'create' => CreatePermission::route('/create'),
            'edit' => EditPermission::route('/{record}/edit'),
        ];
    }
}
