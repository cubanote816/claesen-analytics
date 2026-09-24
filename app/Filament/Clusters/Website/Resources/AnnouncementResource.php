<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Resources;

use App\Filament\Clusters\Website\Resources\AnnouncementResource\Pages;
use App\Filament\Clusters\Website\WebsiteCluster;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Website\Models\Announcement;

/**
 * F3/CLA-469 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * A real CRUD list — unlike SiteSettingsPage's fixed key/value slots, an
 * announcement is genuinely list-shaped content (multiple, dated, with a
 * lifecycle). Still hardcoded to Claesen's site (see
 * ProjectResource::categoryOptions()'s identical rationale) until Bertels
 * has its own panel/resources (F3/F4).
 */
class AnnouncementResource extends Resource
{
    use \App\Filament\Concerns\ScopedToPanelSite;

    protected static ?string $model = Announcement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $cluster = WebsiteCluster::class;

    public static function getNavigationLabel(): string
    {
        return __('website.announcements.plural_label');
    }

    public static function getModelLabel(): string
    {
        return __('website.announcements.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('website.announcements.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Textarea::make('message')
                ->required()
                ->rows(3)
                ->helperText(__('website.announcements.message_helper')),
            DateTimePicker::make('starts_at')
                ->native(false)
                ->helperText(__('website.announcements.starts_at_helper')),
            DateTimePicker::make('ends_at')
                ->native(false)
                ->helperText(__('website.announcements.ends_at_helper')),
            Select::make('status')
                ->options([
                    Announcement::STATUS_DRAFT => __('website.announcements.status.draft'),
                    Announcement::STATUS_PUBLISHED => __('website.announcements.status.published'),
                    Announcement::STATUS_ARCHIVED => __('website.announcements.status.archived'),
                ])
                ->required()
                ->default(Announcement::STATUS_DRAFT)
                ->native(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('message')->limit(60)->wrap(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    Announcement::STATUS_PUBLISHED => 'success',
                    Announcement::STATUS_ARCHIVED => 'gray',
                    default => 'warning',
                }),
                Tables\Columns\TextColumn::make('starts_at')->dateTime()->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime()->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    Announcement::STATUS_DRAFT => __('website.announcements.status.draft'),
                    Announcement::STATUS_PUBLISHED => __('website.announcements.status.published'),
                    Announcement::STATUS_ARCHIVED => __('website.announcements.status.archived'),
                ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
