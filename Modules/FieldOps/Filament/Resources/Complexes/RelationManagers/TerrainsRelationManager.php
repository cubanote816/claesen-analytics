<?php

namespace Modules\FieldOps\Filament\Resources\Complexes\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\FieldOps\Models\TerrainType;

class TerrainsRelationManager extends RelationManager
{
    protected static string $relationship = 'terrains';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('fieldops::resource.terrains.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('fieldops::resource.terrains.fields.name'))->schema([
                // Single field in the admin's current locale (app()->getLocale(),
                // set per-request by SetPanelLocale) — HasAiTranslations
                // auto-translates to the other 3 canonical locales on save.
                TextInput::make('name')
                    ->label(__('fieldops::resource.terrains.fields.name'))
                    ->required(),
            ]),
            Select::make('terrain_type_id')
                ->label(__('fieldops::resource.terrains.fields.terrain_type'))
                ->options(TerrainType::all()->mapWithKeys(fn ($t) => [
                    $t->id => $t->getTranslation('type', app()->getLocale(), false) ?: $t->getTranslation('type', 'nl', false),
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
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('name')
                    ->label(__('fieldops::resource.terrains.fields.name'))
                    ->getStateUsing(fn ($record) =>
                        $record->getTranslation('name', app()->getLocale(), false)
                        ?: $record->getTranslation('name', 'nl', false)
                    )
                    ->searchable(),
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
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by_user_id'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
