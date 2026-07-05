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
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\FieldOps\Filament\Resources\Structures\Pages\CreateStructure;
use Modules\FieldOps\Filament\Resources\Structures\Pages\EditStructure;
use Modules\FieldOps\Filament\Resources\Structures\Pages\ListStructures;
use Modules\FieldOps\Models\AccessType;
use Modules\FieldOps\Models\SafetyType;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\StructureType;

class StructureResource extends Resource
{
    protected static ?string $model = Structure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBoltSlash;

    protected static ?int $navigationSort = 4;

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
        return __('fieldops::resource.structures.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.structures.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.structures.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('structure_type_id')
                    ->label(__('fieldops::resource.structures.fields.structure_type'))
                    ->options(StructureType::all()->mapWithKeys(fn ($t) => [
                        $t->id => $t->getTranslation('name', app()->getLocale(), false)
                            ?: $t->getTranslation('name', 'nl', false),
                    ]))
                    ->searchable()
                    ->nullable(),
                TextInput::make('height')
                    ->label(__('fieldops::resource.structures.fields.height'))
                    ->numeric()
                    ->nullable(),
                TextInput::make('lat')
                    ->label(__('fieldops::resource.structures.fields.lat'))
                    ->numeric()
                    ->nullable(),
                TextInput::make('lng')
                    ->label(__('fieldops::resource.structures.fields.lng'))
                    ->numeric()
                    ->nullable(),
                Select::make('access_type_id')
                    ->label(__('fieldops::resource.structures.fields.access_type'))
                    ->options(AccessType::all()->mapWithKeys(fn ($t) => [
                        $t->id => $t->getTranslation('name', app()->getLocale(), false)
                            ?: $t->getTranslation('name', 'nl', false),
                    ]))
                    ->searchable()
                    ->nullable(),
                Toggle::make('access_active')
                    ->label(__('fieldops::resource.structures.fields.access_active'))
                    ->default(false),
                Select::make('safety_type_id')
                    ->label(__('fieldops::resource.structures.fields.safety_type'))
                    ->options(SafetyType::all()->mapWithKeys(fn ($t) => [
                        $t->id => $t->getTranslation('name', app()->getLocale(), false)
                            ?: $t->getTranslation('name', 'nl', false),
                    ]))
                    ->searchable()
                    ->nullable(),
                Toggle::make('safety_certified')
                    ->label(__('fieldops::resource.structures.fields.safety_certified'))
                    ->default(false),
                TextInput::make('cafca_material_id')
                    ->label(__('fieldops::resource.structures.fields.cafca_material_id'))
                    ->nullable(),
            ])->columns(2),
            Section::make(__('fieldops::resource.structures.fields.info'))->schema([
                // Single field in the admin's current locale (app()->getLocale(),
                // set per-request by SetPanelLocale) — HasAiTranslations
                // auto-translates to the other 3 canonical locales on save.
                Textarea::make('info')
                    ->label(__('fieldops::resource.structures.fields.info'))
                    ->rows(3),
            ])->collapsible()->collapsed(),
            Section::make(__('fieldops::resource.media.section_label'))->schema([
                SpatieMediaLibraryFileUpload::make('photos')
                    ->label(__('fieldops::resource.media.photos'))
                    ->collection('photos')
                    ->image()
                    ->multiple()
                    ->maxSize(10240)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                SpatieMediaLibraryFileUpload::make('documents')
                    ->label(__('fieldops::resource.media.documents'))
                    ->collection('documents')
                    ->multiple()
                    ->maxSize(20480)
                    ->acceptedFileTypes(['application/pdf']),
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
                TextColumn::make('structureType.name')
                    ->label(__('fieldops::resource.structures.fields.structure_type'))
                    ->getStateUsing(fn ($record) =>
                        $record->structureType?->getTranslation('name', app()->getLocale(), false)
                        ?: $record->structureType?->getTranslation('name', 'nl', false)
                    )
                    ->badge()
                    ->color('info'),
                TextColumn::make('height')
                    ->label(__('fieldops::resource.structures.fields.height'))
                    ->suffix(' cm')
                    ->sortable(),
                TextColumn::make('terrains_count')
                    ->label(__('fieldops::resource.structures.fields.terrains_count'))
                    ->counts('terrains')
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
            ->with(['structureType', 'accessType', 'safetyType', 'terrains'])
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListStructures::route('/'),
            'create' => CreateStructure::route('/create'),
            'edit'   => EditStructure::route('/{record}/edit'),
        ];
    }
}
