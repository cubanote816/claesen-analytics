<?php

namespace Modules\FieldOps\Filament\Resources\LuminaireFrames\RelationManagers;

use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;

class LuminairesRelationManager extends RelationManager
{
    protected static string $relationship = 'luminaires';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('fieldops::resource.luminaires.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('luminaire_type_id')
                    ->label(__('fieldops::resource.luminaires.fields.luminaire_type'))
                    ->options(LuminaireType::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    // fo_luminaires.luminaire_type_id is NOT NULL — nullable() here let a
                    // blank submit through to a raw QueryException instead of a form error
                    // (same bug CLA-278 already fixed on the main Create Luminaire page).
                    ->required(),
                Select::make('luminaire_subgroup_id')
                    ->label(__('fieldops::resource.luminaires.fields.subgroup'))
                    ->options(LuminaireSubgroup::orderBy('group_name')->get()
                        ->mapWithKeys(fn ($s) => [$s->id => "{$s->group_name} — {$s->brand}"])
                    )
                    ->searchable()
                    ->nullable(),
                TextInput::make('serial_number')
                    ->label(__('fieldops::resource.luminaires.fields.serial_number'))
                    ->nullable()
                    ->maxLength(100)
                    ->helperText(__('fieldops::resource.luminaire_frames.view.serial_optional_hint')),
                TextInput::make('frame_position')
                    ->label(__('fieldops::resource.luminaires.fields.frame_position'))
                    ->numeric()
                    ->minValue(1)
                    ->nullable()
                    // Same conflict check as the main Create Luminaire form's frame_position
                    // field — the frame is fixed here (owner record), so no luminaire_frame_id
                    // lookup needed. Without this, a duplicate position raised a raw
                    // UniqueConstraintViolationException on fo_luminaires_one_active_per_position.
                    ->rule(function (?Luminaire $record): Closure {
                        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            if (LuminaireResource::hasFramePositionConflict($this->getOwnerRecord()->getKey(), $value, $record)) {
                                $fail(__('fieldops::resource.luminaires.fields.frame_position_conflict'));
                            }
                        };
                    }),
                TextInput::make('frame_x')
                    ->label(__('fieldops::resource.luminaires.fields.frame_x'))
                    ->numeric()
                    ->nullable(),
                TextInput::make('frame_y')
                    ->label(__('fieldops::resource.luminaires.fields.frame_y'))
                    ->numeric()
                    ->nullable(),
                TextInput::make('scale_x')
                    ->label(__('fieldops::resource.luminaires.fields.scale_x'))
                    ->numeric()
                    ->step(0.01)
                    ->nullable(),
                TextInput::make('scale_y')
                    ->label(__('fieldops::resource.luminaires.fields.scale_y'))
                    ->numeric()
                    ->step(0.01)
                    ->nullable(),
                TextInput::make('cafca_material_id')
                    ->label(__('fieldops::resource.luminaires.fields.cafca_material_id'))
                    ->nullable(),
            ])->columns(2),
            Section::make(__('fieldops::resource.luminaires.fields.info'))->schema([
                // Single field in the admin's current locale (app()->getLocale(),
                // set per-request by SetPanelLocale) — HasAiTranslations
                // auto-translates to the other 3 canonical locales on save.
                Textarea::make('info')
                    ->label(__('fieldops::resource.luminaires.fields.info'))
                    ->rows(2),
            ])->collapsible()->collapsed(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('serial_number')
            ->columns([
                TextColumn::make('frame_position')
                    ->label(__('fieldops::resource.luminaires.fields.frame_position'))
                    ->sortable(),
                TextColumn::make('serial_number')
                    ->label(__('fieldops::resource.luminaires.fields.serial_number'))
                    ->searchable(),
                TextColumn::make('luminaireType.name')
                    ->label(__('fieldops::resource.luminaires.fields.luminaire_type'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('subgroup.group_name')
                    ->label(__('fieldops::resource.luminaires.fields.subgroup')),
            ])
            ->defaultSort('frame_position')
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Same normalization as CreateLuminaire::mutateFormDataBeforeCreate()
                        // (the main Create Luminaire page) — kept in sync manually since this
                        // relation manager uses its own independent form.
                        $data['luminaire_subgroup_id'] = isset($data['luminaire_type_id'])
                            ? LuminaireType::find($data['luminaire_type_id'])?->luminaire_subgroup_id
                            : null;
                        $data['serial_number'] = LuminaireResource::resolveSerialNumber($data['serial_number'] ?? null);
                        $data['created_by_user_id'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Mirrors EditLuminaire::mutateFormDataBeforeSave() — clearing the
                        // field must not submit null against the NOT NULL serial_number column.
                        if (array_key_exists('serial_number', $data)) {
                            $data['serial_number'] = LuminaireResource::resolveSerialNumber($data['serial_number']);
                        }

                        return $data;
                    }),
                DeleteAction::make(),
            ]);
    }
}
