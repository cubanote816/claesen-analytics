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
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\FieldOps\Filament\Resources\Terrains\Pages\CreateTerrain;
use Modules\FieldOps\Filament\Resources\Terrains\Pages\EditTerrain;
use Modules\FieldOps\Filament\Resources\Terrains\Pages\ListTerrains;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Models\TerrainType;

class TerrainResource extends Resource
{
    protected static ?string $model = Terrain::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 3;

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
        return __('fieldops::resource.terrains.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.terrains.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.terrains.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('fieldops::resource.terrains.fields.name'))->schema([
                Grid::make(2)->schema([
                    TextInput::make('name.nl')
                        ->label(__('fieldops::resource.terrains.fields.name_nl'))
                        ->required(),
                    TextInput::make('name.en')
                        ->label(__('fieldops::resource.terrains.fields.name_en')),
                    TextInput::make('name.fr')
                        ->label(__('fieldops::resource.terrains.fields.name_fr')),
                    TextInput::make('name.de')
                        ->label(__('fieldops::resource.terrains.fields.name_de')),
                ]),
            ]),
            Section::make()->schema([
                Select::make('complex_id')
                    ->label(__('fieldops::resource.terrains.fields.complex'))
                    ->options(Complex::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('terrain_type_id')
                    ->label(__('fieldops::resource.terrains.fields.terrain_type'))
                    ->options(TerrainType::all()->mapWithKeys(fn ($t) => [
                        $t->id => $t->getTranslation('type', app()->getLocale(), false)
                            ?: $t->getTranslation('type', 'nl', false),
                    ]))
                    ->searchable()
                    ->nullable(),
                TextInput::make('lat')
                    ->label(__('fieldops::resource.terrains.fields.lat'))
                    ->numeric()
                    ->nullable(),
                TextInput::make('lng')
                    ->label(__('fieldops::resource.terrains.fields.lng'))
                    ->numeric()
                    ->nullable(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('fieldops::resource.terrains.fields.name'))
                    ->getStateUsing(fn ($record) =>
                        $record->getTranslation('name', app()->getLocale(), false)
                        ?: $record->getTranslation('name', 'nl', false)
                    )
                    ->searchable(),
                TextColumn::make('complex.name')
                    ->label(__('fieldops::resource.terrains.fields.complex'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('terrainType.type')
                    ->label(__('fieldops::resource.terrains.fields.terrain_type'))
                    ->getStateUsing(fn ($record) =>
                        $record->terrainType?->getTranslation('type', app()->getLocale(), false)
                        ?: $record->terrainType?->getTranslation('type', 'nl', false)
                    )
                    ->badge()
                    ->color('info'),
                TextColumn::make('structures_count')
                    ->label(__('fieldops::resource.terrains.fields.structures_count'))
                    ->counts('structures')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('complex_id')
                    ->label(__('fieldops::resource.terrains.fields.complex'))
                    ->options(Complex::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
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
            ->with(['complex', 'terrainType'])
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListTerrains::route('/'),
            'create' => CreateTerrain::route('/create'),
            'edit'   => EditTerrain::route('/{record}/edit'),
        ];
    }
}
