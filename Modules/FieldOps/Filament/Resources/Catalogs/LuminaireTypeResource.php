<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
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
                TextInput::make('product_family')
                    ->label(__('fieldops::resource.catalogs.fields.product_family'))
                    ->maxLength(255),
                TextInput::make('model_reference')
                    ->label(__('fieldops::resource.catalogs.fields.model_reference'))
                    ->maxLength(255),
                Select::make('luminaire_subgroup_id')
                    ->label(__('fieldops::resource.catalogs.fields.subgroup'))
                    ->options(LuminaireSubgroup::orderBy('group_name')->get()
                        ->mapWithKeys(fn ($s) => [$s->id => "{$s->group_name} — {$s->brand}"])
                    )
                    ->searchable()
                    ->nullable(),
                TextInput::make('image')
                    ->label(__('fieldops::resource.catalogs.fields.image'))
                    ->maxLength(255),
                TextInput::make('image_source_url')
                    ->label(__('fieldops::resource.catalogs.fields.image_source_url'))
                    ->url(),
                Textarea::make('typical_application')
                    ->label(__('fieldops::resource.catalogs.fields.typical_application'))
                    ->rows(3)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Same treatment as LuminaireFrameTypeResource — image is the point here
            // too (the icon drawn on the Luminaire frame canvas), so a visual card
            // grid instead of a plain table.
            ->contentGrid(['md' => 3, 'xl' => 4])
            ->columns([
                ImageColumn::make('image')
                    ->label(__('fieldops::resource.catalogs.fields.image'))
                    ->getStateUsing(fn (LuminaireType $record): ?string => static::resolveImageUrl($record->image))
                    ->size(64)
                    ->extraImgAttributes(['class' => 'rounded-lg object-cover']),
                TextColumn::make('name')
                    ->label(__('fieldops::resource.catalogs.fields.name'))
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product_family')
                    ->label(__('fieldops::resource.catalogs.fields.product_family'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('model_reference')
                    ->label(__('fieldops::resource.catalogs.fields.model_reference'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('subgroup.group_name')
                    ->label(__('fieldops::resource.catalogs.fields.subgroup'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subgroup.brand')
                    ->label(__('fieldops::resource.catalogs.fields.brand')),
                TextColumn::make('luminaires_count')
                    ->label(__('fieldops::resource.catalogs.fields.used_by'))
                    ->counts('luminaires'),
                TextColumn::make('typical_application')
                    ->label(__('fieldops::resource.catalogs.fields.typical_application'))
                    ->limit(80)
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
            'index' => ListLuminaireTypes::route('/'),
            'create' => CreateLuminaireType::route('/create'),
            'edit' => EditLuminaireType::route('/{record}/edit'),
        ];
    }

    protected static function resolveImageUrl(?string $image): ?string
    {
        if (! $image) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, 'data:')) {
            return $image;
        }

        return asset(ltrim($image, '/'));
    }
}
