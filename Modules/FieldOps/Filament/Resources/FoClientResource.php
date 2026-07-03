<?php

namespace Modules\FieldOps\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\FieldOps\Filament\Resources\FoClients\Pages\CreateFoClient;
use Modules\FieldOps\Filament\Resources\FoClients\Pages\EditFoClient;
use Modules\FieldOps\Filament\Resources\FoClients\Pages\ListFoClients;
use Modules\FieldOps\Models\FoClient;

class FoClientResource extends Resource
{
    protected static ?string $model = FoClient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 1;

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
        return __('fieldops::resource.clients.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.clients.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.clients.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->label(__('fieldops::resource.clients.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('street')
                    ->label(__('fieldops::resource.clients.fields.street'))
                    ->maxLength(255),
                TextInput::make('city')
                    ->label(__('fieldops::resource.clients.fields.city'))
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label(__('fieldops::resource.clients.fields.phone'))
                    ->tel()
                    ->maxLength(50),
                TextInput::make('email')
                    ->label(__('fieldops::resource.clients.fields.email'))
                    ->email()
                    ->maxLength(255),
                Select::make('language')
                    ->label(__('fieldops::resource.clients.fields.language'))
                    ->options(['nl' => 'Nederlands', 'en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch'])
                    ->default('nl'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('fieldops::resource.clients.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city')
                    ->label(__('fieldops::resource.clients.fields.city'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('fieldops::resource.clients.fields.email'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('fieldops::resource.clients.fields.phone')),
                TextColumn::make('language')
                    ->label(__('fieldops::resource.clients.fields.language'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('complexes_count')
                    ->label(__('fieldops::resource.clients.fields.complexes_count'))
                    ->counts('complexes')
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListFoClients::route('/'),
            'create' => CreateFoClient::route('/create'),
            'edit'   => EditFoClient::route('/{record}/edit'),
        ];
    }
}
