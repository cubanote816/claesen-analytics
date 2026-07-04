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
use Modules\FieldOps\Filament\Resources\Catalogs\FoMaintenanceTypes\Pages\CreateFoMaintenanceType;
use Modules\FieldOps\Filament\Resources\Catalogs\FoMaintenanceTypes\Pages\EditFoMaintenanceType;
use Modules\FieldOps\Filament\Resources\Catalogs\FoMaintenanceTypes\Pages\ListFoMaintenanceTypes;
use Modules\FieldOps\Models\FoMaintenanceType;

class FoMaintenanceTypeResource extends Resource
{
    protected static ?string $model = FoMaintenanceType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?int $navigationSort = 12;

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
        return __('fieldops::resource.catalogs.maintenance_types');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.catalogs.maintenance_types');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.catalogs.maintenance_types');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('fieldops::resource.catalogs.fields.name'))->schema([
                Grid::make(2)->schema([
                    TextInput::make('name.nl')
                        ->label(__('fieldops::resource.catalogs.fields.name_nl'))
                        ->required(),
                    TextInput::make('name.en')
                        ->label(__('fieldops::resource.catalogs.fields.name_en')),
                    TextInput::make('name.fr')
                        ->label(__('fieldops::resource.catalogs.fields.name_fr')),
                    TextInput::make('name.de')
                        ->label(__('fieldops::resource.catalogs.fields.name_de')),
                ]),
                TextInput::make('code')
                    ->label(__('fieldops::resource.catalogs.fields.code'))
                    ->helperText('preventive | corrective | emergency — leave empty for a custom type')
                    ->maxLength(20),
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
                TextColumn::make('code')
                    ->label(__('fieldops::resource.catalogs.fields.code'))
                    ->badge()
                    ->toggleable(),
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
            'index'  => ListFoMaintenanceTypes::route('/'),
            'create' => CreateFoMaintenanceType::route('/create'),
            'edit'   => EditFoMaintenanceType::route('/{record}/edit'),
        ];
    }
}
