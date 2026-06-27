<?php

namespace Modules\Intelligence\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Intelligence\Services\BiConfigService;

class BiConfigPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $slug = 'bi-config';
    protected static ?int $navigationSort = 90;

    protected string $view = 'intelligence::filament.pages.bi-config';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.intelligence_hub');
    }

    public static function getNavigationLabel(): string
    {
        return __('intelligence::bi_config.navigation_label');
    }

    public function getTitle(): string
    {
        return __('intelligence::bi_config.title');
    }

    public function mount(): void
    {
        $svc = app(BiConfigService::class);

        $typeLabels    = $svc->get('project_type_labels', []);
        $statusLabels  = $svc->get('estimate_status_labels', []);
        $margins       = $svc->get('variant_margin_targets', []);
        $laborSchedule = $svc->get('labor_sync_schedule', []);
        $guardian      = $svc->get('billing_guardian_rules', []);

        $this->form->fill([
            // Section 1
            'type_0' => $typeLabels[0] ?? null,
            'type_1' => $typeLabels[1] ?? null,
            'type_2' => $typeLabels[2] ?? null,
            'type_3' => $typeLabels[3] ?? null,
            'type_4' => $typeLabels[4] ?? null,
            'type_5' => $typeLabels[5] ?? null,
            'type_6' => $typeLabels[6] ?? null,
            'type_7' => $typeLabels[7] ?? null,
            'type_8' => $typeLabels[8] ?? null,
            // Section 2
            'status_0' => $statusLabels['0'] ?? null,
            'status_1' => $statusLabels['1'] ?? null,
            'status_3' => $statusLabels['3'] ?? null,
            'status_4' => $statusLabels['4'] ?? null,
            'status_5' => $statusLabels['5'] ?? null,
            'status_6' => $statusLabels['6'] ?? null,
            'status_7' => $statusLabels['7'] ?? null,
            // Section 3
            'margin_economy'  => $margins['economy']  ?? 20,
            'margin_standard' => $margins['standard'] ?? 27,
            'margin_premium'  => $margins['premium']  ?? 35,
            // Section 4
            'labor_start' => $laborSchedule['start'] ?? null,
            'labor_end'   => $laborSchedule['end']   ?? null,
            // Section 5
            'guardian_days'                => $guardian['days_without_invoice']              ?? 30,
            'guardian_min_amount'          => $guardian['min_amount']                        ?? 500,
            'guardian_min_activity_amount' => $guardian['min_activity_amount']               ?? 500,
            'guardian_min_cost_amount'     => $guardian['min_cost_amount']                   ?? 500,
            'guardian_include_no_contract' => $guardian['include_projects_without_contract'] ?? false,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([

                // ── Section 1: Project type labels ───────────────────────────
                Section::make(__('intelligence::bi_config.sections.project_types'))
                    ->description(__('intelligence::bi_config.sections.project_types_desc'))
                    ->schema([
                        TextInput::make('type_0')->label('Type 0')->placeholder('Industrie'),
                        TextInput::make('type_1')->label('Type 1')->placeholder('Industrie'),
                        TextInput::make('type_2')->label('Type 2')->placeholder('Openbare Verlichting'),
                        TextInput::make('type_3')->label('Type 3')->placeholder('Openbare Verlichting'),
                        TextInput::make('type_4')->label('Type 4')->placeholder('Sportverlichting'),
                        TextInput::make('type_5')->label('Type 5')->placeholder('Sportverlichting'),
                        TextInput::make('type_6')->label('Type 6')->placeholder('Masten'),
                        TextInput::make('type_7')->label('Type 7')->placeholder('Industrie'),
                        TextInput::make('type_8')->label('Type 8')->placeholder('Algemeen'),
                    ])
                    ->columns(3),

                // ── Section 2: Estimate status labels ────────────────────────
                Section::make(__('intelligence::bi_config.sections.estimate_status'))
                    ->description(__('intelligence::bi_config.sections.estimate_status_desc'))
                    ->schema([
                        TextInput::make('status_0')->label('Status 0')->placeholder(__('intelligence::bi_config.fields.status_placeholders.0')),
                        TextInput::make('status_1')->label('Status 1')->placeholder(__('intelligence::bi_config.fields.status_placeholders.1')),
                        TextInput::make('status_3')->label('Status 3')->placeholder(__('intelligence::bi_config.fields.status_placeholders.3')),
                        TextInput::make('status_4')->label(__('intelligence::bi_config.fields.status_4_label'))->placeholder(__('intelligence::bi_config.fields.status_placeholders.4')),
                        TextInput::make('status_5')->label('Status 5')->placeholder(__('intelligence::bi_config.fields.status_placeholders.5')),
                        TextInput::make('status_6')->label('Status 6')->placeholder(__('intelligence::bi_config.fields.status_placeholders.6')),
                        TextInput::make('status_7')->label('Status 7')->placeholder(__('intelligence::bi_config.fields.status_placeholders.7')),
                    ])
                    ->columns(3),

                // ── Section 3: Variant margin targets ────────────────────────
                Section::make(__('intelligence::bi_config.sections.margins'))
                    ->description(__('intelligence::bi_config.sections.margins_desc'))
                    ->schema([
                        TextInput::make('margin_economy')
                            ->label(__('intelligence::bi_config.fields.margin_economy'))
                            ->numeric()->minValue(0)->maxValue(100)->suffix('%')
                            ->helperText(__('intelligence::bi_config.fields.margin_economy_hint')),
                        TextInput::make('margin_standard')
                            ->label(__('intelligence::bi_config.fields.margin_standard'))
                            ->numeric()->minValue(0)->maxValue(100)->suffix('%')
                            ->helperText(__('intelligence::bi_config.fields.margin_standard_hint')),
                        TextInput::make('margin_premium')
                            ->label(__('intelligence::bi_config.fields.margin_premium'))
                            ->numeric()->minValue(0)->maxValue(100)->suffix('%')
                            ->helperText(__('intelligence::bi_config.fields.margin_premium_hint')),
                    ])
                    ->columns(3),

                // ── Section 4: Labor sync schedule ───────────────────────────
                Section::make(__('intelligence::bi_config.sections.labor_sync'))
                    ->description(__('intelligence::bi_config.sections.labor_sync_desc'))
                    ->schema([
                        TimePicker::make('labor_start')
                            ->label(__('intelligence::bi_config.fields.labor_start'))
                            ->seconds(false),
                        TimePicker::make('labor_end')
                            ->label(__('intelligence::bi_config.fields.labor_end'))
                            ->seconds(false),
                    ])
                    ->columns(2)
                    ->footerActions([
                        Action::make('sync_now')
                            ->label(__('intelligence::bi_config.actions.sync_now'))
                            ->icon('heroicon-o-arrow-path')
                            ->color('gray')
                            ->action('runMirrorSync'),
                    ]),

                // ── Section 5: Billing Guardian rules ────────────────────────
                Section::make(__('intelligence::bi_config.sections.guardian'))
                    ->description(__('intelligence::bi_config.sections.guardian_desc'))
                    ->schema([
                        TextInput::make('guardian_days')
                            ->label(__('intelligence::bi_config.fields.guardian_days'))
                            ->numeric()->minValue(1)->suffix(__('intelligence::bi_config.fields.guardian_days_suffix'))
                            ->helperText(__('intelligence::bi_config.fields.guardian_days_hint')),
                        TextInput::make('guardian_min_amount')
                            ->label(__('intelligence::bi_config.fields.guardian_min_amount'))
                            ->numeric()->minValue(0)->prefix('€')
                            ->helperText(__('intelligence::bi_config.fields.guardian_min_amount_hint')),
                        TextInput::make('guardian_min_activity_amount')
                            ->label(__('intelligence::bi_config.fields.guardian_min_activity'))
                            ->numeric()->minValue(0)->prefix('€')
                            ->helperText(__('intelligence::bi_config.fields.guardian_min_activity_hint')),
                        TextInput::make('guardian_min_cost_amount')
                            ->label(__('intelligence::bi_config.fields.guardian_min_cost'))
                            ->numeric()->minValue(0)->prefix('€')
                            ->helperText(__('intelligence::bi_config.fields.guardian_min_cost_hint')),
                        Toggle::make('guardian_include_no_contract')
                            ->label(__('intelligence::bi_config.fields.guardian_no_contract'))
                            ->helperText(__('intelligence::bi_config.fields.guardian_no_contract_hint'))
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('intelligence::bi_config.actions.save'))
                ->submit('save')
                ->color('primary')
                ->icon('heroicon-m-check'),
        ];
    }

    public function save(): void
    {
        $d   = $this->form->getState();
        $svc = app(BiConfigService::class);
        $uid = auth()->id();

        $svc->set('project_type_labels', [
            $d['type_0'], $d['type_1'], $d['type_2'],
            $d['type_3'], $d['type_4'], $d['type_5'],
            $d['type_6'], $d['type_7'], $d['type_8'],
        ], $uid);

        $svc->set('estimate_status_labels', [
            '0' => $d['status_0'],
            '1' => $d['status_1'],
            '3' => $d['status_3'],
            '4' => $d['status_4'],
            '5' => $d['status_5'],
            '6' => $d['status_6'],
            '7' => $d['status_7'],
        ], $uid);

        $svc->set('variant_margin_targets', [
            'economy'  => (int) $d['margin_economy'],
            'standard' => (int) $d['margin_standard'],
            'premium'  => (int) $d['margin_premium'],
        ], $uid);

        $svc->set('labor_sync_schedule', [
            'start' => $d['labor_start'] ?: null,
            'end'   => $d['labor_end']   ?: null,
        ], $uid);

        $svc->set('billing_guardian_rules', [
            'days_without_invoice'              => (int) $d['guardian_days'],
            'min_amount'                        => (int) $d['guardian_min_amount'],
            'min_activity_amount'               => (int) $d['guardian_min_activity_amount'],
            'min_cost_amount'                   => (int) $d['guardian_min_cost_amount'],
            'include_projects_without_contract' => (bool) $d['guardian_include_no_contract'],
        ], $uid);

        Notification::make()
            ->title(__('intelligence::bi_config.notifications.saved_title'))
            ->success()
            ->send();
    }

    public function runMirrorSync(): void
    {
        try {
            \Illuminate\Support\Facades\Artisan::queue('intelligence:sync-mirror');
            Notification::make()
                ->title(__('intelligence::bi_config.notifications.sync_queued_title'))
                ->body(__('intelligence::bi_config.notifications.sync_queued_body'))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('intelligence::bi_config.notifications.sync_failed_title'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
