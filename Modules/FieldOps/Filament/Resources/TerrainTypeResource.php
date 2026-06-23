<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use Modules\FieldOps\Filament\Resources\TerrainTypeResource\Pages;
use Modules\FieldOps\Models\TerrainType;

class TerrainTypeResource extends Resource
{
    use Translatable;

    protected static ?string $model = TerrainType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'type';

    public static function getNavigationGroup(): ?string
    {
        return 'FieldOps & Installations';
    }

    public static function getNavigationLabel(): string
    {
        return 'Terrain Types';
    }

    public static function getModelLabel(): string
    {
        return 'Terrain Type';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Terrain Types';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Terrain Type')
                ->schema([
                    TextInput::make('type')
                        ->label('Type Name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Type Name')
                    ->getStateUsing(fn ($record) => $record->getTranslation('type', app()->getLocale(), false) ?: $record->type)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('terrains_count')
                    ->counts('terrains')
                    ->label('Terrains')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ai_translation_status')
                    ->label('AI Translation')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'complete' => 'success',
                        'pending'  => 'warning',
                        default    => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('type')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasRole('super_admin') ?? false),
                ]),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withCount('terrains');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTerrainTypes::route('/'),
            'create' => Pages\CreateTerrainType::route('/create'),
            'edit'   => Pages\EditTerrainType::route('/{record}/edit'),
        ];
    }
}
