<?php

namespace Modules\FieldOps\Filament\Resources\Complexes\RelationManagers;

use Filament\Actions\Action;
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
 * Complex belongsToMany ElectricalBoard (fo_electrical_board_structure) — same pivot as
 * Structure, but seen from the Complex side. Attach/detach of existing boards, never
 * create/edit (ElectricalBoard has its own resource and lifecycle).
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
                Action::make('attachElectricalBoard')
                    ->label(__('fieldops::resource.electrical_boards.actions.attach'))
                    ->button()
                    ->icon('heroicon-m-plus')
                    ->color('primary')
                    ->modalHeading(__('fieldops::resource.electrical_boards.actions.attach'))
                    ->modalSubmitActionLabel(__('fieldops::resource.electrical_boards.actions.attach'))
                    ->form([
                        Select::make('board_id')
                            ->label(__('fieldops::resource.electrical_boards.fields.location_description'))
                            ->searchable()
                            ->required()
                            ->getSearchResultsUsing(fn (string $search) => ElectricalBoard::query()
                                ->where('location_description->nl', 'like', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (ElectricalBoard $board) => [
                                    $board->id => $board->getTranslation('location_description', app()->getLocale(), false)
                                        ?: $board->getTranslation('location_description', 'nl', false),
                                ]))
                            ->getOptionLabelUsing(function ($value) {
                                $board = ElectricalBoard::find($value);

                                return $board
                                    ? ($board->getTranslation('location_description', app()->getLocale(), false) ?: $board->getTranslation('location_description', 'nl', false))
                                    : null;
                            }),
                    ])
                    ->action(function (array $data): void {
                        $this->getOwnerRecord()->electricalBoards()->syncWithoutDetaching([
                            $data['board_id'],
                        ]);
                    }),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}
