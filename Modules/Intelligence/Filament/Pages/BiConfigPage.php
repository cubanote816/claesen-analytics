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
        return 'Intelligence Hub';
    }

    public static function getNavigationLabel(): string
    {
        return app()->getLocale() === 'nl' ? 'BI Configuratie' : 'BI Configuration';
    }

    public function getTitle(): string
    {
        return app()->getLocale() === 'nl' ? 'BI Configuratie' : 'BI Configuration';
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
            'guardian_min_cost_amount'     => $guardian['min_cost_amount']                   ?? 500,
            'guardian_include_no_contract' => $guardian['include_projects_without_contract'] ?? false,
        ]);
    }

    public function form(Schema $form): Schema
    {
        $nl = app()->getLocale() === 'nl';

        return $form
            ->schema([

                // ── Section 1: Project type labels ───────────────────────────
                Section::make($nl ? 'Projecttype labels' : 'Project type labels')
                    ->description($nl
                        ? 'Namen voor project.type (0-8) uit CAFCA. Leeg = fallback "Tipo N" met waarschuwingsbadge.'
                        : 'Names for project.type (0-8) from CAFCA ERP. Empty = fallback "Type N" with warning badge.')
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
                Section::make($nl ? 'Offertestatus labels' : 'Estimate status labels')
                    ->description($nl
                        ? 'Namen voor estimate.status codes. Status 4 = 91% van alle offertes. Status 3 = beste proxy voor "verzonden naar klant". Status 2 heeft 0 records.'
                        : 'Names for estimate.status codes. Status 4 = 91% of all estimates. Status 3 = best proxy for "sent to client". Status 2 has 0 records.')
                    ->schema([
                        TextInput::make('status_0')->label('Status 0')->placeholder($nl ? 'Niet geclassificeerd' : 'Unclassified'),
                        TextInput::make('status_1')->label('Status 1')->placeholder($nl ? 'Concept' : 'Draft'),
                        TextInput::make('status_3')->label('Status 3')->placeholder($nl ? 'Verzonden naar klant' : 'Sent to client'),
                        TextInput::make('status_4')->label($nl ? 'Status 4 (91%)' : 'Status 4 (91%)')->placeholder($nl ? 'Actieve offerte' : 'Active estimate'),
                        TextInput::make('status_5')->label('Status 5')->placeholder($nl ? 'Interne review' : 'Internal review'),
                        TextInput::make('status_6')->label('Status 6')->placeholder($nl ? 'Verloren / Afgewezen' : 'Lost / Rejected'),
                        TextInput::make('status_7')->label('Status 7')->placeholder($nl ? 'Gearchiveerd' : 'Archived'),
                    ])
                    ->columns(3),

                // ── Section 3: Variant margin targets ────────────────────────
                Section::make($nl ? 'Doelmarges per variant (%)' : 'Variant margin targets (%)')
                    ->description($nl
                        ? 'Winstmarge per offertevariante. Historisch CAFCA-bereik: 15-35%.'
                        : 'Target profit margin per offer variant. Historical CAFCA range: 15-35%.')
                    ->schema([
                        TextInput::make('margin_economy')
                            ->label($nl ? 'Economisch (%)' : 'Economy (%)')
                            ->numeric()->minValue(0)->maxValue(100)->suffix('%')
                            ->helperText($nl ? 'Laagste marge — kostprijs + minimale opslag' : 'Lowest margin — cost price + minimal markup'),
                        TextInput::make('margin_standard')
                            ->label($nl ? 'Standaard (%)' : 'Standard (%)')
                            ->numeric()->minValue(0)->maxValue(100)->suffix('%')
                            ->helperText($nl ? 'Aanbevolen standaard marge' : 'Recommended standard margin'),
                        TextInput::make('margin_premium')
                            ->label($nl ? 'Premium (%)' : 'Premium (%)')
                            ->numeric()->minValue(0)->maxValue(100)->suffix('%')
                            ->helperText($nl ? 'Hoogste marge — complexe of urgente projecten' : 'Highest margin — complex or urgent projects'),
                    ])
                    ->columns(3),

                // ── Section 4: Labor sync schedule ───────────────────────────
                Section::make($nl ? 'Synchronisatie arbeidsuren' : 'Labor sync schedule')
                    ->description($nl
                        ? 'followup_labor_analytical wordt geblokkeerd tijdens actief CAFCA-gebruik. Stel een veilig tijdvenster in (bijv. 22:00-06:00). Leeg = geen beperking.'
                        : 'followup_labor_analytical is locked during active CAFCA use. Set a safe time window (e.g. 22:00-06:00). Empty = no restriction.')
                    ->schema([
                        TimePicker::make('labor_start')
                            ->label($nl ? 'Start veilig venster' : 'Window start')
                            ->seconds(false),
                        TimePicker::make('labor_end')
                            ->label($nl ? 'Einde veilig venster' : 'Window end')
                            ->seconds(false),
                    ])
                    ->columns(2)
                    ->footerActions([
                        Action::make('sync_now')
                            ->label($nl ? 'Sync nu uitvoeren' : 'Run sync now')
                            ->icon('heroicon-o-arrow-path')
                            ->color('gray')
                            ->action('runMirrorSync'),
                    ]),

                // ── Section 5: Billing Guardian rules ────────────────────────
                Section::make($nl ? 'Monthly Billing Guardian regels' : 'Monthly Billing Guardian rules')
                    ->description($nl
                        ? 'Drempelwaarden voor automatische factureringsdetectie. Wijzigingen worden direct actief bij de volgende Guardian-run.'
                        : 'Thresholds for automatic billing detection. Changes take effect on the next Guardian run.')
                    ->schema([
                        TextInput::make('guardian_days')
                            ->label($nl ? 'Dagen zonder factuur' : 'Days without invoice')
                            ->numeric()->minValue(1)->suffix($nl ? 'dagen' : 'days')
                            ->helperText($nl ? 'Actieve projecten zonder factuur na N dagen → alert' : 'Active projects with no invoice after N days → alert'),
                        TextInput::make('guardian_min_amount')
                            ->label($nl ? 'Min. openstaand bedrag (€)' : 'Min. open amount (€)')
                            ->numeric()->minValue(0)->prefix('€')
                            ->helperText($nl ? 'Vervallen facturen onder dit bedrag worden overgeslagen' : 'Overdue invoices below this amount are skipped'),
                        TextInput::make('guardian_min_cost_amount')
                            ->label($nl ? 'Min. niet-gefactureerde kost (€)' : 'Min. unbilled cost (€)')
                            ->numeric()->minValue(0)->prefix('€')
                            ->helperText($nl ? 'Niet-gefactureerde kosten per project/maand onder dit bedrag worden overgeslagen' : 'Unbilled costs per project/month below this amount are skipped'),
                        Toggle::make('guardian_include_no_contract')
                            ->label($nl ? 'Inclusief projecten zonder contract' : 'Include projects without contract')
                            ->helperText($nl ? 'Als uitgeschakeld: projecten zonder contract_price worden overgeslagen' : 'If off: projects without contract_price are skipped')
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
                ->label(app()->getLocale() === 'nl' ? 'Opslaan' : 'Save')
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
            'min_cost_amount'                   => (int) $d['guardian_min_cost_amount'],
            'include_projects_without_contract' => (bool) $d['guardian_include_no_contract'],
        ], $uid);

        $nl = app()->getLocale() === 'nl';
        Notification::make()
            ->title($nl ? 'Configuratie opgeslagen' : 'Configuration saved')
            ->success()
            ->send();
    }

    public function runMirrorSync(): void
    {
        $nl = app()->getLocale() === 'nl';
        try {
            \Illuminate\Support\Facades\Artisan::queue('intelligence:sync-mirror');
            Notification::make()
                ->title($nl ? 'Sync in wachtrij geplaatst' : 'Sync queued')
                ->body($nl ? 'De synchronisatie wordt op de achtergrond uitgevoerd.' : 'Sync is running in the background.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title($nl ? 'Sync mislukt' : 'Sync failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
