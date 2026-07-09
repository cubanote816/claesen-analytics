<?php

namespace Modules\FieldOps\Filament\Resources\Structures\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;
use Modules\FieldOps\Models\ElectricalBoard;

/**
 * Structure belongsToMany ElectricalBoard (fo_electrical_board_structure) — a board
 * is shared infrastructure with no single owner (Pattern C), so this is attach/detach
 * of existing boards, never create/edit.
 */
class ElectricalBoardsRelationManager extends RelationManager
{
    protected static string $relationship = 'electricalBoards';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('fieldops::resource.electrical_boards.plural_label');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->electricalBoards()->count();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->recordUrl(fn ($record) => ElectricalBoardResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('electricalBoardType.name')
                    ->label(__('fieldops::resource.electrical_boards.fields.electrical_board_type'))
                    ->getStateUsing(fn ($record) => $record->electricalBoardType?->getTranslation('name', app()->getLocale(), false)
                        ?: $record->electricalBoardType?->getTranslation('name', 'nl', false))
                    ->badge()
                    ->color('warning'),
                TextColumn::make('location_description')
                    ->label(__('fieldops::resource.electrical_boards.fields.location_description'))
                    ->getStateUsing(fn ($record) => $record->getTranslation('location_description', app()->getLocale(), false)
                        ?: $record->getTranslation('location_description', 'nl', false)),
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelect(fn (Select $select) => $select
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => ElectricalBoard::query()
                            ->where('location_description->nl', 'like', "%{$search}%")
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (ElectricalBoard $board) => [$board->id => $board->getTranslation('location_description', app()->getLocale(), false)
                                ?: $board->getTranslation('location_description', 'nl', false)]))
                        ->getOptionLabelUsing(function ($value) {
                            $board = ElectricalBoard::find($value);

                            return $board
                                ? ($board->getTranslation('location_description', app()->getLocale(), false) ?: $board->getTranslation('location_description', 'nl', false))
                                : null;
                        })),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}
