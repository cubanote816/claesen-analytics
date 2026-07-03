<?php

namespace Modules\FieldOps\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages\CreateElectricalBoard;
use Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages\EditElectricalBoard;
use Modules\FieldOps\Filament\Resources\ElectricalBoards\Pages\ListElectricalBoards;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\ElectricalBoardType;

class ElectricalBoardResource extends Resource
{
    protected static ?string $model = ElectricalBoard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?int $navigationSort = 7;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.field_operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('fieldops::resource.electrical_boards.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.electrical_boards.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.electrical_boards.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('electrical_board_type_id')
                    ->label(__('fieldops::resource.electrical_boards.fields.electrical_board_type'))
                    ->options(ElectricalBoardType::all()->mapWithKeys(fn ($t) => [
                        $t->id => $t->getTranslation('name', app()->getLocale(), false)
                            ?: $t->getTranslation('name', 'nl', false),
                    ]))
                    ->searchable()
                    ->required(),
                TextInput::make('lat')
                    ->label(__('fieldops::resource.electrical_boards.fields.lat'))
                    ->numeric()
                    ->nullable(),
                TextInput::make('lng')
                    ->label(__('fieldops::resource.electrical_boards.fields.lng'))
                    ->numeric()
                    ->nullable(),
            ])->columns(2),
            Section::make(__('fieldops::resource.electrical_boards.fields.location_description'))->schema([
                Grid::make(2)->schema([
                    Textarea::make('location_description.nl')
                        ->label(__('fieldops::resource.electrical_boards.fields.location_description_nl'))
                        ->rows(3),
                    Textarea::make('location_description.en')
                        ->label(__('fieldops::resource.electrical_boards.fields.location_description_en'))
                        ->rows(3),
                    Textarea::make('location_description.fr')
                        ->label(__('fieldops::resource.electrical_boards.fields.location_description_fr'))
                        ->rows(3),
                    Textarea::make('location_description.de')
                        ->label(__('fieldops::resource.electrical_boards.fields.location_description_de'))
                        ->rows(3),
                ]),
            ])->collapsible()->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('electricalBoardType.name')
                    ->label(__('fieldops::resource.electrical_boards.fields.electrical_board_type'))
                    ->getStateUsing(fn ($record) =>
                        $record->electricalBoardType?->getTranslation('name', app()->getLocale(), false)
                        ?: $record->electricalBoardType?->getTranslation('name', 'nl', false)
                    )
                    ->badge()
                    ->color('warning'),
                TextColumn::make('complexes_count')
                    ->label(__('fieldops::resource.electrical_boards.fields.complexes_count'))
                    ->counts('complexes')
                    ->sortable(),
                TextColumn::make('terrains_count')
                    ->label(__('fieldops::resource.electrical_boards.fields.terrains_count'))
                    ->counts('terrains')
                    ->sortable(),
                TextColumn::make('structures_count')
                    ->label(__('fieldops::resource.electrical_boards.fields.structures_count'))
                    ->counts('structures')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['electricalBoardType'])
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListElectricalBoards::route('/'),
            'create' => CreateElectricalBoard::route('/create'),
            'edit'   => EditElectricalBoard::route('/{record}/edit'),
        ];
    }
}
