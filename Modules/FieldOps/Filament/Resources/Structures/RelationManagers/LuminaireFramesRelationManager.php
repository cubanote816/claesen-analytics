<?php

namespace Modules\FieldOps\Filament\Resources\Structures\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\FieldOps\Models\LuminaireFrame;

/**
 * Structure belongsToMany LuminaireFrame (fo_luminaire_frame_structure) — attach/detach
 * of existing frames, never create/edit (a frame's own luminaires are managed from
 * the Luminaire frame resource itself).
 */
class LuminaireFramesRelationManager extends RelationManager
{
    protected static string $relationship = 'luminaireFrames';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('fieldops::resource.luminaire_frames.plural_label');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->luminaireFrames()->count();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('frameType.name')
                    ->label(__('fieldops::resource.luminaire_frames.fields.frame_type'))
                    ->formatStateUsing(fn ($record) => '#'.$record->id.' — '.$record->frameType?->name)
                    ->badge()
                    ->color('info'),
                TextColumn::make('luminaires_count')
                    ->label(__('fieldops::resource.luminaire_frames.fields.luminaires_count'))
                    ->counts('luminaires'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelect(fn (Select $select) => $select
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => LuminaireFrame::query()
                            ->whereHas('frameType', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                            ->orWhere('id', 'like', "%{$search}%")
                            ->with('frameType')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (LuminaireFrame $frame) => [$frame->id => '#'.$frame->id.' — '.$frame->frameType?->name]))
                        ->getOptionLabelUsing(function ($value) {
                            $frame = LuminaireFrame::with('frameType')->find($value);

                            return $frame ? '#'.$frame->id.' — '.$frame->frameType?->name : null;
                        })),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}
