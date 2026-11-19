<?php

declare(strict_types=1);

namespace App\Filament\Bertels\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Modules\Core\Filament\Resources\Users\Tables\UsersTable;

/**
 * CLA-704 S10: Claesen's columns, row action and bulk delete. No organization filter: the
 * section only ever holds one organization. Records come from the Bertels-scoped resource
 * query, so a bulk delete can only reach Bertels users.
 */
class BertelsUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(UsersTable::columns())
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
