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
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireFrameTypes\Pages\CreateLuminaireFrameType;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireFrameTypes\Pages\EditLuminaireFrameType;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireFrameTypes\Pages\ListLuminaireFrameTypes;
use Modules\FieldOps\Models\LuminaireFrameType;

class LuminaireFrameTypeResource extends Resource
{
    protected static ?string $model = LuminaireFrameType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

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
        return __('fieldops::resource.catalogs.frame_types');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.catalogs.frame_types');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.catalogs.frame_types');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                TextInput::make('name')
                    ->label(__('fieldops::resource.catalogs.fields.name'))
                    ->required()
                    ->maxLength(255),
                ViewField::make('image')
                    ->label(__('fieldops::resource.catalogs.fields.image'))
                    ->helperText(__('fieldops::resource.catalogs.frame_type_editor.helper'))
                    ->view('fieldops::filament.forms.luminaire-frame-type-image-editor')
                    ->viewData([
                        'uploadUrl' => route('fieldops.catalogs.luminaire-frame-types.image-store'),
                    ])
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Only 2 of the 8 FieldOps catalogs carry a real image (this one and
            // LuminaireType) — the image IS the point (it becomes the icon drawn on
            // the Luminaire frame canvas), so it gets a visual card grid instead of
            // a plain table. The other 6 catalogs are pure text and stay as a list.
            ->contentGrid(['md' => 3, 'xl' => 4])
            ->columns([
                ImageColumn::make('image')
                    ->label(__('fieldops::resource.catalogs.fields.image'))
                    ->getStateUsing(fn (LuminaireFrameType $record): ?string => static::resolveImageUrl($record->image))
                    ->size(64)
                    ->extraImgAttributes(['class' => 'rounded-lg object-cover']),
                TextColumn::make('name')
                    ->label(__('fieldops::resource.catalogs.fields.name'))
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('luminaire_frames_count')
                    ->label(__('fieldops::resource.catalogs.fields.used_by'))
                    ->counts('luminaireFrames'),
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
            'index'  => ListLuminaireFrameTypes::route('/'),
            'create' => CreateLuminaireFrameType::route('/create'),
            'edit'   => EditLuminaireFrameType::route('/{record}/edit'),
        ];
    }

    // Same treatment as LuminaireTypeResource: 'image' can be a seeded /assets/...
    // path (served straight from public/, not the storage disk) or a real upload
    // stored on the 'public' disk (frame_type_editor's upload/draw flow). Filament's
    // ImageColumn resolves bare state through Storage::disk('public')->exists(),
    // which is always false for the seeded path, so it renders nothing without this.
    protected static function resolveImageUrl(?string $image): ?string
    {
        if (! $image) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, 'data:')) {
            return $image;
        }

        if (Storage::disk('public')->exists($image)) {
            return Storage::disk('public')->url($image);
        }

        return asset(ltrim($image, '/'));
    }
}
