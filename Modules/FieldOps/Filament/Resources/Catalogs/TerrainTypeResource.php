<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
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
use Modules\FieldOps\Support\TerrainPinCatalog;

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
                // Single field in the admin's current locale (app()->getLocale(),
                // set per-request by SetPanelLocale) — HasAiTranslations
                // auto-translates to the other 3 canonical locales on save.
                TextInput::make('type')
                    ->label(__('fieldops::resource.catalogs.fields.type'))
                    ->required(),
                ColorPicker::make('pin_color')
                    ->label(__('fieldops::resource.catalogs.fields.pin_color'))
                    ->nullable(),
            ]),
            Section::make(__('fieldops::resource.catalogs.pin_selector.label'))->schema([
                ViewField::make('code')
                    ->label(__('fieldops::resource.catalogs.pin_selector.label'))
                    ->helperText(__('fieldops::resource.catalogs.pin_selector.helper'))
                    ->view('fieldops::filament.forms.terrain-type-pin-selector')
                    ->viewData([
                        'pins' => TerrainPinCatalog::definitions(),
                    ])
                    // The "Generic" card binds to '' (HTML radio values can't be null);
                    // `code` is nullable+unique in fo_terrain_types, so this must
                    // normalize back to null before save — otherwise a second row left
                    // on "Generic" would violate the unique index on an empty string.
                    ->dehydrateStateUsing(fn (?string $state) => $state === '' ? null : $state),
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
                TextColumn::make('code')
                    ->label(__('fieldops::resource.catalogs.pin_selector.label'))
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->code
                        ? (TerrainPinCatalog::find($record->code) ? __(TerrainPinCatalog::find($record->code)['labelKey']) : $record->code)
                        : __('fieldops::resource.catalogs.pin_selector.generic_label'))
                    ->color(fn ($record) => Color::hex($record->pin_color ?: '#e6007e')),
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
