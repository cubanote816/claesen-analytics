<?php

namespace Modules\Core\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\Resources\ActivityLogResource\Pages\ListActivityLogEntries;
use Modules\Core\Models\ActivityLogEntry;

/**
 * F2/CLA-465 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * "Acceso a auditoría restringido y auditado": restricted here to
 * super_admin only (canAccess(), same allowlist pattern as UserResource) —
 * "auditado" is satisfied by this resource itself being read-only (no
 * create/edit/delete surface at all, matching ActivityLogEntry's own
 * append-only Eloquent guard) rather than by logging page views, which has
 * no precedent anywhere else in this codebase and was judged
 * disproportionate to add just for this.
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLogEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.user_management');
    }

    public static function getModelLabel(): string
    {
        return __('activity_log/resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('activity_log/resource.plural_model_label');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('activity_log/resource.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('log_name')
                    ->label(__('activity_log/resource.fields.log_name'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('activity_log/resource.fields.description'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('causer.name')
                    ->label(__('activity_log/resource.fields.causer'))
                    ->default('—'),
                TextColumn::make('subject_type')
                    ->label(__('activity_log/resource.fields.subject_type'))
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),
                TextColumn::make('organization.name')
                    ->label(__('activity_log/resource.fields.organization'))
                    ->default('—'),
                TextColumn::make('site_id')
                    ->label(__('activity_log/resource.fields.site_id'))
                    ->default('—'),
                TextColumn::make('correlation_id')
                    ->label(__('activity_log/resource.fields.correlation_id'))
                    ->copyable()
                    ->limit(8)
                    ->default('—'),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label(__('activity_log/resource.fields.log_name'))
                    ->options(fn () => ActivityLogEntry::query()
                        ->whereNotNull('log_name')
                        ->distinct()
                        ->orderBy('log_name')
                        ->pluck('log_name', 'log_name')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogEntries::route('/'),
        ];
    }
}
