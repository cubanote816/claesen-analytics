<?php

namespace Modules\FieldOps\Filament\Resources\Luminaires\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Contracts\Support\Htmlable;
use Modules\Cafca\Models\Employee;
use Modules\FieldOps\Filament\Resources\FoMaintenanceWorkOrderResource;
use Modules\FieldOps\Filament\Resources\LuminaireFrameResource;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireType;
use Modules\FieldOps\Services\LuminaireReplacementService;
use Modules\FieldOps\Services\LuminaireRemovalService;
use Livewire\Attributes\Locked;

class ViewLuminaire extends ViewRecord
{
    protected static string $resource = LuminaireResource::class;

    #[Locked]
    public ?int $contextStructureId = null;

    #[Locked]
    public ?int $contextTerrainId = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Livewire action requests do not reliably retain the original query
        // string, so preserve the breadcrumb context while the page mounts.
        $this->contextStructureId = request()->integer('via_structure') ?: null;
        $this->contextTerrainId = request()->integer('via_terrain') ?: null;
    }

    // See ViewTerrain::getResourceBreadcrumbs() / FieldOpsBreadcrumbs docblock.
    public function getResourceBreadcrumbs(): array
    {
        return FieldOpsBreadcrumbs::luminaireAncestors(
            $this->getRecord(),
            $this->contextStructureId,
            $this->contextTerrainId,
        );
    }

    // See ViewFoClient::getTitle() for why this skips Filament's "View :label" wrapper.
    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openFrame')
                ->label(__('fieldops::resource.luminaires.actions.open_in_frame'))
                ->icon('heroicon-m-map')
                ->color('gray')
                ->visible(fn (): bool => $this->record->luminaire_frame_id !== null)
                ->url(fn (): string => LuminaireFrameResource::getUrl('view', array_filter([
                    'record' => $this->record->luminaire_frame_id,
                    'layout' => 'technical',
                    'luminaire' => $this->record->id,
                    'via_structure' => request()->integer('via_structure') ?: null,
                    'via_terrain' => request()->integer('via_terrain') ?: null,
                ]))),
            Action::make('scheduleMaintenance')
                ->label(__('fieldops::resource.luminaires.actions.schedule_maintenance'))
                ->icon('heroicon-m-clipboard-document-check')
                ->color('primary')
                ->visible(fn (): bool => $this->record->removed_at === null && $this->record->active_position_id !== null)
                ->url(fn (): string => FoMaintenanceWorkOrderResource::getUrl('create', array_filter([
                    'maintainable_type' => Luminaire::class,
                    'maintainable_id' => $this->record->id,
                    'via_structure' => request()->integer('via_structure') ?: null,
                    'via_terrain' => request()->integer('via_terrain') ?: null,
                ]))),
            Action::make('replaceLuminaire')
                ->label(__('fieldops::resource.luminaires.actions.replace'))
                ->icon('heroicon-m-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->record->removed_at === null && $this->record->active_position_id !== null)
                ->modalHeading(__('fieldops::resource.luminaires.replacement.title'))
                ->modalDescription(__('fieldops::resource.luminaires.replacement.description'))
                ->modalWidth('7xl')
                ->modalSubmitActionLabel(__('fieldops::resource.luminaires.replacement.confirm'))
                ->fillForm(fn (): array => [
                    'maintenance_at' => now(),
                    'position_version' => (int) ($this->record->position?->position_version ?? $this->record->position_version ?? 1),
                ])
                ->schema([
                    Section::make(__('fieldops::resource.luminaires.replacement.current_installation'))
                        ->description(fn (): string => collect([
                            $this->record->luminaireType?->product_family ?: $this->record->luminaireType?->name,
                            $this->record->serial_number,
                            '#'.$this->record->frame_position,
                        ])->filter()->join(' · ')),
                    ViewField::make('luminaire_type_id')
                        ->label(__('fieldops::resource.luminaires.replacement.new_product'))
                        ->view('fieldops::filament.forms.luminaire-type-gallery-selector')
                        ->viewData(['types' => LuminaireResource::buildLuminaireTypeChoices()])
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $set('luminaire_subgroup_id', $state ? LuminaireType::find($state)?->luminaire_subgroup_id : null);
                        })
                        ->required()
                        ->columnSpanFull(),
                    Hidden::make('luminaire_subgroup_id')->required(),
                    Hidden::make('position_version')->required(),
                    TextInput::make('serial_number')
                        ->label(__('fieldops::resource.luminaires.fields.serial_number'))
                        ->required()
                        ->unique(table: Luminaire::class, column: 'serial_number', ignoreRecord: false)
                        ->validationMessages([
                            'unique' => __('fieldops::resource.luminaires.replacement.serial_taken'),
                        ])
                        ->maxLength(50),
                    DateTimePicker::make('maintenance_at')
                        ->label(__('fieldops::resource.maintenance_records.fields.maintenance_at'))
                        ->required(),
                    Select::make('employee_id')
                        ->label(__('fieldops::resource.maintenance_records.fields.employee'))
                        ->options(fn (): array => Employee::orderBy('name')->pluck('name', 'id')->all())
                        ->searchable(),
                    Textarea::make('replacement_reason')
                        ->label(__('fieldops::resource.luminaires.replacement.reason'))
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
                    Textarea::make('root_cause')
                        ->label(__('fieldops::resource.maintenance_records.fields.root_cause'))
                        ->rows(2),
                    Textarea::make('solution_applied')
                        ->label(__('fieldops::resource.maintenance_records.fields.solution_applied'))
                        ->rows(2),
                    Textarea::make('notes')
                        ->label(__('fieldops::resource.maintenance_records.fields.notes'))
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $data['luminaire_subgroup_id'] = LuminaireType::findOrFail($data['luminaire_type_id'])->luminaire_subgroup_id;
                    $result = app(LuminaireReplacementService::class)->replace($this->record, $data, auth()->id());

                    Notification::make()
                        ->title(__('fieldops::resource.luminaires.replacement.completed'))
                        ->success()
                        ->send();

                    $this->redirect(LuminaireResource::getUrl('view', ['record' => $result['current']]), navigate: true);
                }),
            Action::make('removeLuminaire')
                ->label(__('fieldops::resource.luminaires.actions.remove'))
                ->icon('heroicon-m-archive-box-x-mark')
                ->color('danger')
                ->visible(fn (): bool => $this->record->removed_at === null && $this->record->active_position_id !== null)
                ->modalHeading(__('fieldops::resource.luminaires.removal.title'))
                ->modalDescription(__('fieldops::resource.luminaires.removal.description'))
                ->modalSubmitActionLabel(__('fieldops::resource.luminaires.removal.confirm'))
                ->fillForm(fn (): array => [
                    'maintenance_at' => now(),
                    'position_version' => (int) ($this->record->position?->position_version ?? $this->record->position_version ?? 1),
                ])
                ->schema([
                    DateTimePicker::make('maintenance_at')
                        ->label(__('fieldops::resource.maintenance_records.fields.maintenance_at'))
                        ->required(),
                    Hidden::make('position_version')->required(),
                    Textarea::make('removal_reason')
                        ->label(__('fieldops::resource.luminaires.removal.reason'))
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
                    Textarea::make('root_cause')
                        ->label(__('fieldops::resource.maintenance_records.fields.root_cause'))
                        ->rows(2),
                    Textarea::make('notes')
                        ->label(__('fieldops::resource.maintenance_records.fields.notes'))
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $result = app(LuminaireRemovalService::class)->remove($this->record, $data, auth()->id());

                    $this->redirect(LuminaireFrameResource::getUrl('view', [
                        'record' => $result['luminaire']->luminaire_frame_id,
                        'layout' => 'technical',
                        'vacant_position' => $result['luminaire']->luminaire_position_id,
                        'via_structure' => $this->contextStructureId,
                        'via_terrain' => $this->contextTerrainId,
                    ]), navigate: true);
                }),
            EditAction::make(),
        ];
    }
}
