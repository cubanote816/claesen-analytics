<?php

declare(strict_types=1);

namespace Tests\Fixtures\DocumentationPanel;

use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Modules\Core\Models\User;

/** CLA-728 fixture: a resource the inventory reads on a test panel. Never registered on a real panel. */
class GadgetResource extends Resource
{
    protected static ?string $model = User::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Actions::make([
                Action::make('shown'),
                // Hidden until some state holds: still a button the guide must explain.
                Action::make('hidden_until_ready')->visible(false),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [RolesRelationManager::make()];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGadgets::route('/'),
            'create' => Pages\CreateGadget::route('/create'),
            'view' => Pages\ViewGadget::route('/{record}'),
        ];
    }
}
