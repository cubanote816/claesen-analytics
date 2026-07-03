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
use Filament\Forms\Components\Textarea;
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
use Modules\FieldOps\Filament\Resources\Luminaires\Pages\CreateLuminaire;
use Modules\FieldOps\Filament\Resources\Luminaires\Pages\EditLuminaire;
use Modules\FieldOps\Filament\Resources\Luminaires\Pages\ListLuminaires;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;

class LuminaireResource extends Resource
{
    protected static ?string $model = Luminaire::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static ?int $navigationSort = 6;

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
        return __('fieldops::resource.luminaires.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.luminaires.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.luminaires.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('luminaire_frame_id')
                    ->label(__('fieldops::resource.luminaires.fields.frame'))
                    ->options(LuminaireFrame::with('frameType')
                        ->get()
                        ->mapWithKeys(fn ($f) => [
                            $f->id => "#{$f->id} — {$f->frameType?->name}",
                        ])
                    )
                    ->searchable()
                    ->required(),
                Select::make('luminaire_type_id')
                    ->label(__('fieldops::resource.luminaires.fields.luminaire_type'))
                    ->options(LuminaireType::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
                Select::make('luminaire_subgroup_id')
                    ->label(__('fieldops::resource.luminaires.fields.subgroup'))
                    ->options(LuminaireSubgroup::orderBy('group_name')->get()
                        ->mapWithKeys(fn ($s) => [$s->id => "{$s->group_name} — {$s->brand}"])
                    )
                    ->searchable()
                    ->nullable(),
                TextInput::make('serial_number')
                    ->label(__('fieldops::resource.luminaires.fields.serial_number'))
                    ->nullable()
                    ->maxLength(100),
                TextInput::make('frame_position')
                    ->label(__('fieldops::resource.luminaires.fields.frame_position'))
                    ->numeric()
                    ->nullable(),
                TextInput::make('frame_x')
                    ->label(__('fieldops::resource.luminaires.fields.frame_x'))
                    ->numeric()
                    ->nullable(),
                TextInput::make('frame_y')
                    ->label(__('fieldops::resource.luminaires.fields.frame_y'))
                    ->numeric()
                    ->nullable(),
                TextInput::make('cafca_material_id')
                    ->label(__('fieldops::resource.luminaires.fields.cafca_material_id'))
                    ->nullable(),
            ])->columns(2),
            Section::make(__('fieldops::resource.luminaires.fields.info'))->schema([
                Grid::make(2)->schema([
                    Textarea::make('info.nl')
                        ->label(__('fieldops::resource.luminaires.fields.info_nl'))
                        ->rows(2),
                    Textarea::make('info.en')
                        ->label(__('fieldops::resource.luminaires.fields.info_en'))
                        ->rows(2),
                    Textarea::make('info.fr')
                        ->label(__('fieldops::resource.luminaires.fields.info_fr'))
                        ->rows(2),
                    Textarea::make('info.de')
                        ->label(__('fieldops::resource.luminaires.fields.info_de'))
                        ->rows(2),
                ]),
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
                TextColumn::make('luminaireFrame.id')
                    ->label(__('fieldops::resource.luminaires.fields.frame'))
                    ->formatStateUsing(fn ($state) => "Frame #{$state}")
                    ->sortable(),
                TextColumn::make('frame_position')
                    ->label(__('fieldops::resource.luminaires.fields.frame_position'))
                    ->sortable(),
                TextColumn::make('serial_number')
                    ->label(__('fieldops::resource.luminaires.fields.serial_number'))
                    ->searchable(),
                TextColumn::make('luminaireType.name')
                    ->label(__('fieldops::resource.luminaires.fields.luminaire_type'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('subgroup.group_name')
                    ->label(__('fieldops::resource.luminaires.fields.subgroup')),
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
            ->with(['luminaireFrame.frameType', 'luminaireType', 'subgroup'])
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListLuminaires::route('/'),
            'create' => CreateLuminaire::route('/create'),
            'edit'   => EditLuminaire::route('/{record}/edit'),
        ];
    }
}
