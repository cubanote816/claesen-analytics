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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\FieldOps\Filament\Resources\Catalogs\StructureTypes\Pages\CreateStructureType;
use Modules\FieldOps\Filament\Resources\Catalogs\StructureTypes\Pages\EditStructureType;
use Modules\FieldOps\Filament\Resources\Catalogs\StructureTypes\Pages\ListStructureTypes;
use Modules\FieldOps\Models\StructureType;

class StructureTypeResource extends Resource
{
    protected static ?string $model = StructureType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 11;

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
        return __('fieldops::resource.catalogs.structure_types');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.catalogs.structure_types');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.catalogs.structure_types');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('fieldops::resource.catalogs.fields.name'))->schema([
                // Single field in the admin's current locale (app()->getLocale(),
                // set per-request by SetPanelLocale) — HasAiTranslations
                // auto-translates to the other 3 canonical locales on save.
                TextInput::make('name')
                    ->label(__('fieldops::resource.catalogs.fields.name'))
                    ->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('fieldops::resource.catalogs.fields.name'))
                    ->getStateUsing(fn ($record) =>
                        $record->getTranslation('name', app()->getLocale(), false)
                        ?: $record->getTranslation('name', 'nl', false)
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
            'index'  => ListStructureTypes::route('/'),
            'create' => CreateStructureType::route('/create'),
            'edit'   => EditStructureType::route('/{record}/edit'),
        ];
    }
}
