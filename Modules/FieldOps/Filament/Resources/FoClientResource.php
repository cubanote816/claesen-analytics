<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\FieldOps\Filament\Resources\FoClientResource\Pages;
use Modules\FieldOps\Models\FoClient;

class FoClientResource extends Resource
{
    protected static ?string $model = FoClient::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'FieldOps & Installations';
    }

    public static function getNavigationLabel(): string
    {
        return 'Clients';
    }

    public static function getModelLabel(): string
    {
        return 'Client';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Clients';
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
            Section::make('Client Information')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),
                    TextInput::make('city')
                        ->maxLength(255),
                    TextInput::make('street')
                        ->maxLength(255),
                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(50),
                    TextInput::make('email')
                        ->email()
                        ->maxLength(255),
                    Select::make('language')
                        ->options([
                            'nl' => 'Nederlands',
                            'fr' => 'Français',
                            'de' => 'Deutsch',
                            'en' => 'English',
                        ])
                        ->default('nl')
                        ->required(),
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
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('complexes_count')
                    ->counts('complexes')
                    ->label('Complexes')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('language')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'nl' => 'info',
                        'fr' => 'warning',
                        'de' => 'success',
                        'en' => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->searchable()
            ->filters([])
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
        return parent::getEloquentQuery()->withCount('complexes');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFoClients::route('/'),
            'create' => Pages\CreateFoClient::route('/create'),
            'edit'   => Pages\EditFoClient::route('/{record}/edit'),
        ];
    }
}
