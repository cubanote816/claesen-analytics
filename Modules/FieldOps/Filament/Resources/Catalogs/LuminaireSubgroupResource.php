<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireSubgroups\Pages\CreateLuminaireSubgroup;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireSubgroups\Pages\EditLuminaireSubgroup;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireSubgroups\Pages\ListLuminaireSubgroups;
use Modules\FieldOps\Models\LuminaireSubgroup;

class LuminaireSubgroupResource extends Resource
{
    protected static ?string $model = LuminaireSubgroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 13;

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
        return __('fieldops::resource.catalogs.luminaire_subgroups');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.catalogs.luminaire_subgroups');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.catalogs.luminaire_subgroups');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('group_name')
                    ->label(__('fieldops::resource.catalogs.fields.group_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('brand')
                    ->label(__('fieldops::resource.catalogs.fields.brand'))
                    ->required()
                    ->maxLength(255),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('group_name')
                    ->label(__('fieldops::resource.catalogs.fields.group_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand')
                    ->label(__('fieldops::resource.catalogs.fields.brand'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('luminaireTypes_count')
                    ->label(__('fieldops::resource.catalogs.luminaire_types'))
                    ->counts('luminaireTypes')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListLuminaireSubgroups::route('/'),
            'create' => CreateLuminaireSubgroup::route('/create'),
            'edit'   => EditLuminaireSubgroup::route('/{record}/edit'),
        ];
    }
}
