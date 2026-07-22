<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources;

use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\FieldOps\Enums\MaintenanceRequestStatus;
use Modules\FieldOps\Filament\Resources\MaintenanceRequests\Pages\ListMaintenanceRequests;
use Modules\FieldOps\Filament\Resources\MaintenanceRequests\Pages\ViewMaintenanceRequest;
use Modules\FieldOps\Models\FoMaintenanceRequest;
use Modules\FieldOps\Models\FoMaintenanceRequestMessage;

class FoMaintenanceRequestResource extends Resource
{
    protected static ?string $model = FoMaintenanceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?int $navigationSort = 7;

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
        return 'Service requests';
    }

    public static function getModelLabel(): string
    {
        return 'Service request';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Service requests';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Request')->schema([
                Textarea::make('description')->disabled()->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Context')->schema([
                TextEntry::make('client.name')->label('Client'),
                TextEntry::make('status')->badge(),
                TextEntry::make('category')->placeholder('—'),
                TextEntry::make('impact')->placeholder('—'),
                TextEntry::make('maintainable_type')
                    ->label('Asset')
                    ->formatStateUsing(fn ($state, FoMaintenanceRequest $record): string => class_basename($state).' #'.$record->maintainable_id),
                TextEntry::make('description')->columnSpanFull(),
                TextEntry::make('installation_snapshot')->columnSpanFull(),
            ])->columns(2),
            Section::make('Public conversation')->schema([
                TextEntry::make('public_timeline')
                    ->hiddenLabel()
                    ->state(fn (FoMaintenanceRequest $record): string => self::timeline($record, FoMaintenanceRequestMessage::VISIBILITY_PUBLIC))
                    ->markdown()
                    ->columnSpanFull(),
            ]),
            Section::make('Internal notes')->schema([
                TextEntry::make('internal_timeline')
                    ->hiddenLabel()
                    ->state(fn (FoMaintenanceRequest $record): string => self::timeline($record, FoMaintenanceRequestMessage::VISIBILITY_INTERNAL))
                    ->markdown()
                    ->columnSpanFull(),
            ]),
            Section::make('Attachments')->schema([
                TextEntry::make('attachment_names')
                    ->hiddenLabel()
                    ->state(fn (FoMaintenanceRequest $record): string => $record->getMedia('attachments')
                        ->map(fn ($media): string => '- '.e($media->file_name).' ('.$media->human_readable_size.')')
                        ->implode("\n") ?: 'No attachments.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('#')->sortable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('client.name')->label('Client')->searchable(),
            TextColumn::make('maintainable_type')
                ->label('Asset')
                ->formatStateUsing(fn ($state, FoMaintenanceRequest $record): string => class_basename($state).' #'.$record->maintainable_id),
            TextColumn::make('impact')->placeholder('—'),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(self::statusOptions()),
        ])->recordActions([
            ViewAction::make(),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceRequests::route('/'),
            'view' => ViewMaintenanceRequest::route('/{record}'),
        ];
    }

    public static function statusOptions(): array
    {
        return collect(MaintenanceRequestStatus::cases())
            ->mapWithKeys(fn ($status): array => [
                $status->value => str($status->value)->replace('_', ' ')->title()->toString(),
            ])->all();
    }

    private static function timeline(FoMaintenanceRequest $record, string $visibility): string
    {
        return $record->messages()
            ->with('user')
            ->where('visibility', $visibility)
            ->get()
            ->map(function ($message): string {
                $author = $message->user?->name ?? 'System';
                $time = $message->created_at?->format('Y-m-d H:i') ?? '';

                return "**{$author} · {$time}**\n\n{$message->body}";
            })
            ->implode("\n\n---\n\n") ?: 'No messages.';
    }
}
