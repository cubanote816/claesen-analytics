<?php

namespace Modules\FieldOps\Filament\Resources\Structures\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\FieldOps\Filament\Resources\LuminaireFrameResource;
use Modules\FieldOps\Models\LuminaireFrame;

/**
 * Structure belongsToMany LuminaireFrame (fo_luminaire_frame_structure) — attach/detach
 * of existing frames and a create shortcut that links the new frame back to this
 * structure. The frame's own luminaires are still managed from its resource page.
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
                Action::make('createLuminaireFrame')
                    ->label(__('fieldops::resource.luminaire_frames.actions.create'))
                    ->button()
                    ->icon('heroicon-m-plus')
                    ->color('primary')
                    ->visible(fn () => $this->getOwnerRecord()->hasLuminaireFrameCapacity())
                    ->url(LuminaireFrameResource::getUrl('create', [
                        'structure_ids' => [$this->getOwnerRecord()->getKey()],
                    ])),
                AttachAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->hasLuminaireFrameCapacity())
                    ->recordSelect(fn (Select $select) => $select
                        ->searchable()
                        // Hiding the button above is UI-only — re-check here too, in case
                        // the action is somehow reached with a stale page state.
                        ->rule(function (): \Closure {
                            return function (string $attribute, mixed $value, \Closure $fail): void {
                                if (! $this->getOwnerRecord()->hasLuminaireFrameCapacity()) {
                                    $fail(__('fieldops::resource.luminaire_frames.validation.structure_capacity_exceeded'));
                                }
                            };
                        })
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
