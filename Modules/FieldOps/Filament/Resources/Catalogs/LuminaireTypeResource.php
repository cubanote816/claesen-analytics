<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireTypes\Pages\CreateLuminaireType;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireTypes\Pages\EditLuminaireType;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireTypes\Pages\ListLuminaireTypes;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;

class LuminaireTypeResource extends Resource
{
    protected static ?string $model = LuminaireType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 14;

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
        return __('fieldops::resource.catalogs.luminaire_types');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.catalogs.luminaire_types');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.catalogs.luminaire_types');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->label(__('fieldops::resource.catalogs.fields.name'))
                    ->required()
                    ->maxLength(255),
                Select::make('luminaire_subgroup_id')
                    ->label(__('fieldops::resource.catalogs.fields.subgroup'))
                    ->options(LuminaireSubgroup::orderBy('group_name')->get()
                        ->mapWithKeys(fn ($s) => [$s->id => "{$s->group_name} — {$s->brand}"])
                    )
                    ->searchable()
                    ->nullable(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('fieldops::resource.catalogs.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subgroup.group_name')
                    ->label(__('fieldops::resource.catalogs.fields.subgroup'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subgroup.brand')
                    ->label(__('fieldops::resource.catalogs.fields.brand')),
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
            'index'  => ListLuminaireTypes::route('/'),
            'create' => CreateLuminaireType::route('/create'),
            'edit'   => EditLuminaireType::route('/{record}/edit'),
        ];
    }
}
