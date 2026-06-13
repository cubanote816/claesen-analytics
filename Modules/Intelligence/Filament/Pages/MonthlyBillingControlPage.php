<?php

namespace Modules\Intelligence\Filament\Pages;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Intelligence\Models\BillingAlert;
use Modules\Intelligence\Services\MonthlyBillingGuardianService;
use Modules\Performance\Models\Mirror\MirrorInvoice;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Performance\Models\Mirror\MirrorRelation;
use Modules\Performance\Models\ProjectInsight;

class MonthlyBillingControlPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $slug = 'billing-control';
    protected static ?int $navigationSort = 30;

    protected string $view = 'intelligence::filament.pages.billing-control';

    public string $period = '';
    public string $activeTab = 'all';
    public ?int $selectedAlertId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'project_manager']) ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Intelligence Hub';
    }

    public static function getNavigationLabel(): string
    {
        return app()->getLocale() === 'nl' ? 'Facturatiebeheer' : 'Billing Control';
    }

    public function getTitle(): string
    {
        return app()->getLocale() === 'nl' ? 'Maandelijkse Facturatiecontrole' : 'Monthly Billing Control';
    }

    public function mount(): void
    {
        $now = Carbon::now('Europe/Brussels');
        $this->period = sprintf('%d-%02d', $now->year, $now->month);
    }

    public function getPeriodLabel(): string
    {
        [$y, $m] = $this->parsePeriod();

        return Carbon::create($y, $m, 1)->translatedFormat('F Y');
    }

    public function getKpis(): array
    {
        [$year, $month] = $this->parsePeriod();

        $base = BillingAlert::where('period_year', $year)->where('period_month', $month);

        return [
            'total'      => (clone $base)->count(),
            'open'       => (clone $base)->where('status', BillingAlert::STATUS_OPEN)->count(),
            'in_review'  => (clone $base)->where('status', BillingAlert::STATUS_IN_REVIEW)->count(),
            'confirmed'  => (clone $base)->where('status', BillingAlert::STATUS_CONFIRMED)->count(),
            'dismissed'  => (clone $base)->where('status', BillingAlert::STATUS_DISMISSED)->count(),
            'resolved'   => (clone $base)->where('status', BillingAlert::STATUS_RESOLVED)->count(),
            'critical'   => (clone $base)->where('severity', 'critical')->whereIn('status', [BillingAlert::STATUS_OPEN, BillingAlert::STATUS_IN_REVIEW])->count(),
            'high'       => (clone $base)->where('severity', 'high')->whereIn('status', [BillingAlert::STATUS_OPEN, BillingAlert::STATUS_IN_REVIEW])->count(),
            'medium'     => (clone $base)->where('severity', 'medium')->whereIn('status', [BillingAlert::STATUS_OPEN, BillingAlert::STATUS_IN_REVIEW])->count(),
            'low'        => (clone $base)->where('severity', 'low')->whereIn('status', [BillingAlert::STATUS_OPEN, BillingAlert::STATUS_IN_REVIEW])->count(),
            'blocker'    => (clone $base)->where('alert_type', 'monthly_close_blocker')->whereIn('status', [BillingAlert::STATUS_OPEN, BillingAlert::STATUS_IN_REVIEW])->exists(),
        ];
    }

    public function getAlerts(): \Illuminate\Database\Eloquent\Collection
    {
        [$year, $month] = $this->parsePeriod();

        $query = BillingAlert::where('period_year', $year)
            ->where('period_month', $month)
            ->orderByRaw("FIELD(severity, 'critical','high','medium','low') ASC")
            ->orderByRaw("FIELD(status, 'open','in_review','confirmed','dismissed','resolved') ASC");

        if ($this->activeTab !== 'all') {
            $typeMap = [
                'invoicing'   => ['missing_customer_invoice', 'project_billing_gap'],
                'receivables' => ['overdue_receivable', 'partial_payment'],
                'costs'       => ['unbilled_followup_cost', 'closed_with_balance'],
                'credits'     => ['credit_note'],
                'system'      => ['monthly_close_blocker'],
            ];
            $types = $typeMap[$this->activeTab] ?? [];
            if (!empty($types)) {
                $query->whereIn('alert_type', $types);
            }
        }

        return $query->get();
    }

    public function getTabCounts(): array
    {
        [$year, $month] = $this->parsePeriod();

        $counts = BillingAlert::where('period_year', $year)
            ->where('period_month', $month)
            ->selectRaw('alert_type, COUNT(*) as cnt')
            ->groupBy('alert_type')
            ->pluck('cnt', 'alert_type');

        return [
            'all'        => $counts->sum(),
            'invoicing'  => ($counts['missing_customer_invoice'] ?? 0) + ($counts['project_billing_gap'] ?? 0),
            'receivables'=> ($counts['overdue_receivable'] ?? 0) + ($counts['partial_payment'] ?? 0),
            'costs'      => ($counts['unbilled_followup_cost'] ?? 0) + ($counts['closed_with_balance'] ?? 0),
            'credits'    => $counts['credit_note'] ?? 0,
            'system'     => $counts['monthly_close_blocker'] ?? 0,
        ];
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function setPeriod(string $period): void
    {
        if (preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->period = $period;
            $this->activeTab = 'all';
        }
    }

    // -------------------------------------------------------------------------
    // BI-059 — Workflow transitions
    // -------------------------------------------------------------------------

    public function markInReview(int $alertId): void
    {
        $alert = BillingAlert::find($alertId);
        if (!$alert || $alert->status !== BillingAlert::STATUS_OPEN) {
            return;
        }
        $alert->update([
            'status'      => BillingAlert::STATUS_IN_REVIEW,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        Notification::make()->title('Alert in review geplaatst.')->success()->send();
    }

    public function confirmAlert(int $alertId, string $notes = ''): void
    {
        $alert = BillingAlert::find($alertId);
        if (!$alert || $alert->status !== BillingAlert::STATUS_IN_REVIEW) {
            return;
        }
        $alert->update([
            'status'           => BillingAlert::STATUS_CONFIRMED,
            'resolution_notes' => $notes ?: null,
        ]);
        Notification::make()
            ->title(app()->getLocale() === 'nl' ? 'Melding bevestigd.' : 'Alert confirmed.')
            ->body(app()->getLocale() === 'nl'
                ? 'Voer de actie uit in CAFCA en klik daarna op Oplossen.'
                : 'Take the required action in CAFCA, then click Resolve.')
            ->success()
            ->send();
    }

    public function dismissAlert(int $alertId, string $notes = ''): void
    {
        $alert = BillingAlert::find($alertId);
        if (!$alert || $alert->status !== BillingAlert::STATUS_IN_REVIEW) {
            return;
        }
        $alert->update([
            'status'           => BillingAlert::STATUS_DISMISSED,
            'resolution_notes' => $notes ?: null,
        ]);
        Notification::make()
            ->title(app()->getLocale() === 'nl' ? 'Melding afgewezen.' : 'Alert dismissed.')
            ->body(app()->getLocale() === 'nl'
                ? 'Gebruik Heropenen als dit onjuist blijkt.'
                : 'Use Reopen if this turns out to be incorrect.')
            ->info()
            ->send();
    }

    public function resolveAlert(int $alertId, string $notes = ''): void
    {
        $alert = BillingAlert::find($alertId);
        if (!$alert || !in_array($alert->status, [BillingAlert::STATUS_CONFIRMED, BillingAlert::STATUS_DISMISSED], true)) {
            return;
        }
        $alert->update([
            'status'           => BillingAlert::STATUS_RESOLVED,
            'resolved_at'      => now(),
            'resolution_notes' => $notes ?: $alert->resolution_notes,
        ]);
        Notification::make()
            ->title(app()->getLocale() === 'nl' ? 'Melding opgelost.' : 'Alert resolved.')
            ->body(app()->getLocale() === 'nl'
                ? 'De melding telt niet meer mee voor de maandafsluiting.'
                : 'This alert no longer counts towards the monthly close.')
            ->success()
            ->send();
    }

    public function reopenAlert(int $alertId): void
    {
        $alert = BillingAlert::find($alertId);
        if (!$alert || $alert->status !== BillingAlert::STATUS_DISMISSED) {
            return;
        }
        $alert->update([
            'status'           => BillingAlert::STATUS_OPEN,
            'resolution_notes' => null,
        ]);
        Notification::make()->title('Alert heropend.')->warning()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run_guardian')
                ->label(app()->getLocale() === 'nl' ? 'Guardian uitvoeren' : 'Run Guardian')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(app()->getLocale() === 'nl' ? 'Guardian uitvoeren?' : 'Run Guardian?')
                ->modalDescription(
                    app()->getLocale() === 'nl'
                        ? 'Voer de Guardian uit nadat u wijzigingen in CAFCA heeft opgeslagen, of aan het begin van de maand voor de analyse van de vorige periode. Open meldingen worden bijgewerkt met de laatste gegevens. Bevestigde en afgesloten meldingen worden NIET gewijzigd.'
                        : 'Run after saving changes in CAFCA, or at the start of the month to analyse the previous period. Open alerts are updated with the latest data. Confirmed and resolved alerts are NOT modified.'
                )
                ->action(function () {
                    [$year, $month] = $this->parsePeriod();
                    $guardian = app(MonthlyBillingGuardianService::class);
                    $report   = $guardian->analyzeMonth($year, $month);

                    Notification::make()
                        ->title(
                            app()->getLocale() === 'nl'
                                ? "Guardian klaar — {$report->totalDetected} alert(s) gevonden"
                                : "Guardian complete — {$report->totalDetected} alert(s) found"
                        )
                        ->body(
                            app()->getLocale() === 'nl'
                                ? "{$report->created} nieuw, {$report->updated} bijgewerkt, {$report->skipped} overgeslagen."
                                : "{$report->created} created, {$report->updated} updated, {$report->skipped} skipped."
                        )
                        ->success()
                        ->send();
                }),
        ];
    }

    // -------------------------------------------------------------------------
    // BI-2B-UX-02 — Detail modal (lazy load by PK — no N+1)
    // -------------------------------------------------------------------------

    public function openModal(int $alertId): void
    {
        $this->selectedAlertId = $alertId;
    }

    public function closeModal(): void
    {
        $this->selectedAlertId = null;
    }

    /**
     * Lazy context for the detail modal. Called only when selectedAlertId is set.
     * All lookups are by PK — maximum 5 queries, zero N+1.
     *
     * @return array{alert: ?BillingAlert, project: mixed, relation: mixed, invoice: mixed, hasInsight: bool}
     */
    public function getModalData(): array
    {
        if (!$this->selectedAlertId) {
            return [];
        }

        $alert = BillingAlert::find($this->selectedAlertId);
        if (!$alert) {
            return [];
        }

        $project    = $alert->project_id  ? MirrorProject::find($alert->project_id)  : null;
        $relId      = $project?->relation_id ?? $alert->relation_id;
        $relation   = $relId               ? MirrorRelation::find($relId)             : null;
        $invoice    = $alert->invoice_id   ? MirrorInvoice::find($alert->invoice_id)  : null;
        $hasInsight = $alert->project_id
            ? ProjectInsight::where('project_id', $alert->project_id)->exists()
            : false;

        return compact('alert', 'project', 'relation', 'invoice', 'hasInsight');
    }

    // -------------------------------------------------------------------------
    // BI-2B-UX-03 — Project / relation / insight context (no N+1)
    // -------------------------------------------------------------------------

    /**
     * Returns maps keyed by ID for the alerts currently visible in the active tab.
     * Four indexed whereIn queries total — zero N+1.
     *
     * @return array{
     *   projects: \Illuminate\Support\Collection,
     *   relations: \Illuminate\Support\Collection,
     *   insightSet: array<string, int>
     * }
     */
    public function getProjectContext(): array
    {
        [$year, $month] = $this->parsePeriod();

        $base = BillingAlert::where('period_year', $year)->where('period_month', $month);

        if ($this->activeTab !== 'all') {
            $typeMap = [
                'invoicing'   => ['missing_customer_invoice', 'project_billing_gap'],
                'receivables' => ['overdue_receivable', 'partial_payment'],
                'costs'       => ['unbilled_followup_cost', 'closed_with_balance'],
                'credits'     => ['credit_note'],
                'system'      => ['monthly_close_blocker'],
            ];
            $types = $typeMap[$this->activeTab] ?? [];
            if (!empty($types)) {
                $base->whereIn('alert_type', $types);
            }
        }

        $alertRows = $base->get(['project_id', 'relation_id']);

        $projectIds  = $alertRows->pluck('project_id')->filter()->unique()->values()->all();
        $alertRelIds = $alertRows->pluck('relation_id')->filter()->unique()->values()->all();

        $projects = $projectIds
            ? MirrorProject::whereIn('id', $projectIds)->get(['id', 'name', 'relation_id'])->keyBy('id')
            : collect();

        $allRelationIds = collect($alertRelIds)
            ->merge($projects->pluck('relation_id')->filter())
            ->unique()
            ->values()
            ->all();

        $relations = $allRelationIds
            ? MirrorRelation::whereIn('id', $allRelationIds)->get(['id', 'name'])->keyBy('id')
            : collect();

        $insightSet = $projectIds
            ? ProjectInsight::whereIn('project_id', $projectIds)->pluck('project_id')->flip()->toArray()
            : [];

        return compact('projects', 'relations', 'insightSet');
    }

    private function parsePeriod(): array
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $this->period, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        $now = Carbon::now('Europe/Brussels');

        return [$now->year, $now->month];
    }
}
