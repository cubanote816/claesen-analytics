<?php

namespace Modules\FieldOps\Filament\Resources\Structures\RelationManagers;

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
 * Structure belongsToMany ElectricalBoard (fo_electrical_board_structure) — a board
 * is shared infrastructure with no single owner (Pattern C), so this is attach/detach
 * of existing boards and a create shortcut that re-links the new board back to the
 * structure, preserving any terrains the structure already spans.
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
            ->recordUrl(fn ($record) => ElectricalBoardResource::getUrl('view', [
                'record' => $record,
                'via_structure' => $this->getOwnerRecord()->getKey(),
            ]))
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
                Action::make('createElectricalBoard')
                    ->label(__('fieldops::resource.electrical_boards.actions.create'))
                    ->button()
                    ->icon('heroicon-m-plus')
                    ->color('primary')
                    ->url(ElectricalBoardResource::getUrl('create', [
                        'structure_ids' => [$this->getOwnerRecord()->getKey()],
                        'terrain_ids' => $this->getOwnerRecord()->terrains()->pluck('fo_terrains.id')->all(),
                    ])),
                Action::make('attach')
                    ->label(__('fieldops::resource.electrical_boards.actions.attach'))
                    ->button()
                    ->icon('heroicon-m-plus')
                    ->color('gray')
                    ->modalWidth('2xl')
                    ->extraModalWindowAttributes([
                        'style' => 'z-index: 9999;',
                    ])
                    ->schema([
                        Select::make('recordId')
                            ->label(__('fieldops::resource.electrical_boards.fields.location_description'))
                            ->searchable()
                            ->required()
                            ->getSearchResultsUsing(fn (string $search) => ElectricalBoard::query()
                                ->where(function ($query) use ($search): void {
                                    $query
                                        ->where('location_description->nl', 'like', "%{$search}%")
                                        ->orWhere('location_description->en', 'like', "%{$search}%")
                                        ->orWhere('location_description->fr', 'like', "%{$search}%")
                                        ->orWhere('location_description->de', 'like', "%{$search}%");
                                })
                                ->orderBy('id', 'desc')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (ElectricalBoard $board) => [
                                    $board->id => $board->getTranslation('location_description', app()->getLocale(), false)
                                        ?: $board->getTranslation('location_description', 'nl', false)
                                        ?: '#'.$board->id,
                                ]))
                            ->getOptionLabelUsing(function ($value) {
                                $board = ElectricalBoard::find($value);

                                return $board
                                    ? ($board->getTranslation('location_description', app()->getLocale(), false)
                                        ?: $board->getTranslation('location_description', 'nl', false)
                                        ?: '#'.$board->id)
                                    : null;
                            })
                            ->options(fn (): array => ElectricalBoard::query()
                                ->orderBy('id', 'desc')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (ElectricalBoard $board) => [
                                    $board->id => $board->getTranslation('location_description', app()->getLocale(), false)
                                        ?: $board->getTranslation('location_description', 'nl', false)
                                        ?: '#'.$board->id,
                                ])
                                ->all()),
                    ])
                    ->action(function (array $data): void {
                        $this->getOwnerRecord()->electricalBoards()->syncWithoutDetaching([
                            $data['recordId'],
                        ]);
                    }),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}
