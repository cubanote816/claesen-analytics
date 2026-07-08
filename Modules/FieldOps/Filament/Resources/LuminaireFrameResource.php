<?php

namespace Modules\FieldOps\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages\CreateLuminaireFrame;
use Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages\EditLuminaireFrame;
use Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages\ListLuminaireFrames;
use Modules\FieldOps\Filament\Resources\LuminaireFrames\Pages\ViewLuminaireFrame;
use Modules\FieldOps\Filament\Resources\LuminaireFrames\RelationManagers\LuminairesRelationManager;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireFrameType;

class LuminaireFrameResource extends Resource
{
    protected static ?string $model = LuminaireFrame::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?int $navigationSort = 5;

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
        return __('fieldops::resource.luminaire_frames.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('fieldops::resource.luminaire_frames.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fieldops::resource.luminaire_frames.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('luminaire_frame_type_id')
                    ->label(__('fieldops::resource.luminaire_frames.fields.frame_type'))
                    ->options(LuminaireFrameType::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextEntry::make('frameType.name')
                    ->label(__('fieldops::resource.luminaire_frames.fields.frame_type'))
                    ->placeholder('—')
                    ->badge()
                    ->color('info'),
                TextEntry::make('luminaires_count')
                    ->label(__('fieldops::resource.luminaire_frames.fields.luminaires_count'))
                    ->state(fn (LuminaireFrame $record) => (string) $record->luminaires()->count()),
            ])->columns(2),

            Section::make(__('fieldops::resource.luminaire_frames.canvas_label'))
                ->schema([
                    ViewEntry::make('canvas')
                        ->hiddenLabel()
                        ->state(fn (LuminaireFrame $record) => static::buildCanvasMarkers($record))
                        ->default(fn () => [])
                        ->view('fieldops::filament.infolists.luminaire-canvas'),
                ]),
        ]);
    }

    /**
     * frame_x/frame_y have no declared value range in the schema, so markers are
     * normalized to the actual min/max of this frame's luminaires (with padding)
     * instead of assuming a fixed 0-100 scale. scale_x drives marker size; a marker
     * is flagged (amber) when its luminaire has an open maintenance issue —
     * problem_reported_at set, problem_solved_at still null.
     *
     * @return array<int, array{id: int, left: float, top: float, size: int, label: string, serial: ?string, flagged: bool, url: string}>
     */
    protected static function buildCanvasMarkers(LuminaireFrame $record): array
    {
        $luminaires = $record->luminaires()->with('luminaireType')->orderBy('frame_position')->get();

        if ($luminaires->isEmpty()) {
            return [];
        }

        $xs = $luminaires->pluck('frame_x')->filter(fn ($v) => $v !== null);
        $ys = $luminaires->pluck('frame_y')->filter(fn ($v) => $v !== null);
        $minX = $xs->min() ?? 0.0;
        $minY = $ys->min() ?? 0.0;
        $rangeX = max(($xs->max() ?? 100) - $minX, 1);
        $rangeY = max(($ys->max() ?? 100) - $minY, 1);

        return $luminaires->map(function (Luminaire $luminaire) use ($minX, $rangeX, $minY, $rangeY) {
            $hasOpenIssue = $luminaire->maintenanceRecords()
                ->whereNotNull('problem_reported_at')
                ->whereNull('problem_solved_at')
                ->exists();

            return [
                'id' => $luminaire->id,
                'left' => round(10 + (($luminaire->frame_x - $minX) / $rangeX) * 80, 1),
                'top' => round(10 + (($luminaire->frame_y - $minY) / $rangeY) * 80, 1),
                'size' => (int) round(28 * max((float) ($luminaire->scale_x ?? 1.0), 0.5)),
                'label' => (string) ($luminaire->frame_position ?? '?'),
                'serial' => $luminaire->serial_number,
                'flagged' => $hasOpenIssue,
                'url' => \Modules\FieldOps\Filament\Resources\LuminaireResource::getUrl('edit', ['record' => $luminaire]),
            ];
        })->values()->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('frameType.name')
                    ->label(__('fieldops::resource.luminaire_frames.fields.frame_type'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('luminaires_count')
                    ->label(__('fieldops::resource.luminaire_frames.fields.luminaires_count'))
                    ->counts('luminaires')
                    ->sortable(),
                TextColumn::make('structures_count')
                    ->label(__('fieldops::resource.luminaire_frames.fields.structures_count'))
                    ->counts('structures')
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
                ViewAction::make(),
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

    public static function getRelations(): array
    {
        return [
            LuminairesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['frameType'])
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListLuminaireFrames::route('/'),
            'create' => CreateLuminaireFrame::route('/create'),
            'view'   => ViewLuminaireFrame::route('/{record}'),
            'edit'   => EditLuminaireFrame::route('/{record}/edit'),
        ];
    }
}
