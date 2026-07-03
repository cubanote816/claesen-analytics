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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\FieldOps\Filament\Resources\Complexes\Pages\CreateComplex;
use Modules\FieldOps\Filament\Resources\Complexes\Pages\EditComplex;
use Modules\FieldOps\Filament\Resources\Complexes\Pages\ListComplexes;
use Modules\FieldOps\Filament\Resources\Complexes\RelationManagers\TerrainsRelationManager;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\FoClient;

class ComplexResource extends Resource
{
    protected static ?string $model = Complex::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?int $navigationSort = 2;

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
        return __('fieldops::resource.complexes.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.complexes.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.complexes.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->label(__('fieldops::resource.complexes.fields.name'))
                    ->required()
                    ->maxLength(255),
                Select::make('client_id')
                    ->label(__('fieldops::resource.complexes.fields.client'))
                    ->options(FoClient::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
                TextInput::make('street')
                    ->label(__('fieldops::resource.complexes.fields.street'))
                    ->maxLength(255),
                TextInput::make('city')
                    ->label(__('fieldops::resource.complexes.fields.city'))
                    ->maxLength(255),
                TextInput::make('zipcode')
                    ->label(__('fieldops::resource.complexes.fields.zipcode'))
                    ->maxLength(20),
                TextInput::make('lat')
                    ->label(__('fieldops::resource.complexes.fields.lat'))
                    ->numeric()
                    ->nullable(),
                TextInput::make('lng')
                    ->label(__('fieldops::resource.complexes.fields.lng'))
                    ->numeric()
                    ->nullable(),
                TextInput::make('zoom')
                    ->label(__('fieldops::resource.complexes.fields.zoom'))
                    ->numeric()
                    ->default(17.0)
                    ->nullable(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('fieldops::resource.complexes.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city')
                    ->label(__('fieldops::resource.complexes.fields.city'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label(__('fieldops::resource.complexes.fields.client'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('terrains_count')
                    ->label(__('fieldops::resource.complexes.fields.terrains_count'))
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

    public static function getRelations(): array
    {
        return [
            TerrainsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListComplexes::route('/'),
            'create' => CreateComplex::route('/create'),
            'edit'   => EditComplex::route('/{record}/edit'),
        ];
    }
}
