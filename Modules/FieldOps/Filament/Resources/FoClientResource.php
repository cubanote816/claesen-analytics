<?php

namespace Modules\FieldOps\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\FieldOps\Filament\Resources\FoClients\Pages\ListFoClients;
use Modules\FieldOps\Filament\Resources\FoClients\Pages\ViewFoClient;
use Modules\FieldOps\Models\FoClient;

class FoClientResource extends Resource
{
    protected static ?string $model = FoClient::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('fieldops::resource.clients.model_label'))
                ->icon(Heroicon::OutlinedUser)
                ->schema([
                    Group::make([
                        TextEntry::make('name')
                            ->label(__('fieldops::resource.clients.fields.name'))
                            ->weight('bold')
                            ->size(TextSize::Large),
                        TextEntry::make('language')
                            ->label(__('fieldops::resource.clients.fields.language'))
                            ->badge()
                            ->color('info'),
                    ]),
                    Group::make([
                        TextEntry::make('phone')
                            ->label(__('fieldops::resource.clients.fields.phone'))
                            ->icon(Heroicon::OutlinedPhone)
                            ->placeholder('—')
                            ->url(fn ($record) => $record->phone ? 'tel:'.$record->phone : null),
                        TextEntry::make('email')
                            ->label(__('fieldops::resource.clients.fields.email'))
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->placeholder('—')
                            ->url(fn ($record) => $record->email ? 'mailto:'.$record->email : null),
                        TextEntry::make('address')
                            ->label(__('fieldops::resource.clients.fields.address'))
                            ->icon(Heroicon::OutlinedMapPin)
                            ->state(fn ($record) => collect([$record->street, $record->city])->filter()->implode(', ') ?: null)
                            ->placeholder('—')
                            ->url(fn ($record) => ($record->street || $record->city)
                                ? 'https://www.google.com/maps/search/?api=1&query='.urlencode(collect([$record->street, $record->city])->filter()->implode(', '))
                                : null)
                            ->openUrlInNewTab(),
                    ]),
                ])
                ->columns(2),

            Section::make(__('fieldops::resource.clients.fields.complexes_count'))
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->schema([
                    ViewEntry::make('complexes')
                        ->hiddenLabel()
                        // Filament's Entry::getState() falls back to null on an empty
                        // relationship collection (Laravel's blank() treats a 0-count
                        // Collection as blank) — ->default() is what backfills it, so
                        // the blade's @forelse always gets an iterable, never null.
                        ->default(fn () => collect())
                        ->view('fieldops::filament.infolists.associated-complexes'),
                ]),
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
            'index' => ListFoClients::route('/'),
            'view'  => ViewFoClient::route('/{record}'),
        ];
    }
}
