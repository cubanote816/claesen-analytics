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
use Modules\FieldOps\Filament\Resources\StructureTypeResource\Pages;
use Modules\FieldOps\Models\StructureType;

class StructureTypeResource extends Resource
{
    use Translatable;

    protected static ?string $model = StructureType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'FieldOps & Installations';
    }

    public static function getNavigationLabel(): string
    {
        return 'Structure Types';
    }

    public static function getModelLabel(): string
    {
        return 'Structure Type';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Structure Types';
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
            Section::make('Structure Type')
                ->schema([
                    TextInput::make('name')
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Type Name')
                    ->getStateUsing(fn ($record) => $record->getTranslation('name', app()->getLocale(), false) ?: $record->name)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('structures_count')
                    ->counts('structures')
                    ->label('Structures')
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
            ->defaultSort('name')
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
        return parent::getEloquentQuery()->withCount('structures');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStructureTypes::route('/'),
            'create' => Pages\CreateStructureType::route('/create'),
            'edit'   => Pages\EditStructureType::route('/{record}/edit'),
        ];
    }
}
