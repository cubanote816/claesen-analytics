<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
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
use Modules\FieldOps\Filament\Resources\Catalogs\TerrainTypes\Pages\CreateTerrainType;
use Modules\FieldOps\Filament\Resources\Catalogs\TerrainTypes\Pages\EditTerrainType;
use Modules\FieldOps\Filament\Resources\Catalogs\TerrainTypes\Pages\ListTerrainTypes;
use Modules\FieldOps\Models\TerrainType;

class TerrainTypeResource extends Resource
{
    protected static ?string $model = TerrainType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.field_operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('fieldops::resource.catalogs.terrain_types');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.catalogs.terrain_types');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.catalogs.terrain_types');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('fieldops::resource.catalogs.fields.type'))->schema([
                Grid::make(2)->schema([
                    TextInput::make('type.nl')
                        ->label(__('fieldops::resource.catalogs.fields.type_nl'))
                        ->required(),
                    TextInput::make('type.en')
                        ->label(__('fieldops::resource.catalogs.fields.type_en')),
                    TextInput::make('type.fr')
                        ->label(__('fieldops::resource.catalogs.fields.type_fr')),
                    TextInput::make('type.de')
                        ->label(__('fieldops::resource.catalogs.fields.type_de')),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label(__('fieldops::resource.catalogs.fields.type'))
                    ->getStateUsing(fn ($record) =>
                        $record->getTranslation('type', app()->getLocale(), false)
                        ?: $record->getTranslation('type', 'nl', false)
                    )
                    ->searchable()
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
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListTerrainTypes::route('/'),
            'create' => CreateTerrainType::route('/create'),
            'edit'   => EditTerrainType::route('/{record}/edit'),
        ];
    }
}
