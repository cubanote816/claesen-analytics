<?php

namespace Modules\Intelligence\Filament\Pages;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Modules\Intelligence\Models\BillingAlert;
use Modules\Intelligence\Services\MonthlyBillingGuardianService;
use Modules\Performance\Models\Mirror\MirrorInvoice;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Performance\Models\Mirror\MirrorRelation;
use Modules\Performance\Models\ProjectInsight;

/**
 * BI-2B-UX-09 — Billing Control page restructured by business question.
 *
 * Five sections instead of technical tabs:
 *   1. Nog te factureren (missing invoice / unbilled cost / billing gap) — period-filtered
 *   2. Vervallen facturen (overdue receivable) — all active, NOT period-filtered
 *   3. Afgesloten projecten met open saldo (closed_with_balance) — all active, NOT period-filtered
 *   4. Creditnota's (credit_note) — period-filtered
 *   5. Maandafsluiting — summary + Run Guardian button
 */
class MonthlyBillingControlPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $slug = 'billing-control';
    protected static ?int $navigationSort = 30;

    protected string $view = 'intelligence::filament.pages.billing-control';

    public static function getNavigationBadge(): ?string
    {
        return 'BETA';
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public string $period = '';
    public ?int $selectedAlertId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'project_manager']) ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.intelligence_hub');
    }

    public static function getNavigationLabel(): string
    {
        return __('intelligence::billing.navigation_label');
    }

    public function getTitle(): string
    {
        return __('intelligence::billing.title');
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

    public function setPeriod(string $period): void
    {
        if (preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->period = $period;
        }
    }

    // -------------------------------------------------------------------------
    // Section queries
    // -------------------------------------------------------------------------

    /** Section 1: Nog te factureren — filtered by selected period. */
    public function getBillingAlerts(): Collection
    {
        [$year, $month] = $this->parsePeriod();

        return BillingAlert::where('period_year', $year)
            ->where('period_month', $month)
            ->whereIn('alert_type', ['missing_customer_invoice', 'unbilled_followup_cost', 'project_billing_gap'])
            ->orderByRaw("FIELD(severity,'critical','high','medium','low') ASC")
            ->orderByRaw("FIELD(status,'open','in_review','confirmed','dismissed','resolved') ASC")
            ->get();
    }

    /** Section 2: Vervallen facturen — ALL active, not period-filtered. */
    public function getOverdueAlerts(): Collection
    {
        return BillingAlert::where('alert_type', 'overdue_receivable')
            ->whereNotIn('status', [BillingAlert::STATUS_RESOLVED, BillingAlert::STATUS_DISMISSED])
            ->orderByRaw("FIELD(severity,'critical','high','medium','low') ASC")
            ->orderByRaw("FIELD(status,'open','in_review','confirmed') ASC")
            ->get();
    }

    /** Section 3: Afgesloten projecten met open saldo — ALL active, not period-filtered. */
    public function getClosedBalanceAlerts(): Collection
    {
        return BillingAlert::where('alert_type', 'closed_with_balance')
            ->whereNotIn('status', [BillingAlert::STATUS_RESOLVED, BillingAlert::STATUS_DISMISSED])
            ->orderByRaw("FIELD(severity,'critical','high','medium','low') ASC")
            ->orderByRaw("FIELD(status,'open','in_review','confirmed') ASC")
            ->get();
    }

    /** Section 4: Creditnota's — filtered by selected period. */
    public function getCreditNoteAlerts(): Collection
    {
        [$year, $month] = $this->parsePeriod();

        return BillingAlert::where('period_year', $year)
            ->where('period_month', $month)
            ->where('alert_type', 'credit_note')
            ->orderByRaw("FIELD(status,'open','in_review','confirmed','dismissed','resolved') ASC")
            ->get();
    }

    /** Section 5: Maandafsluiting — summary data for the selected period. */
    public function getMaandafsluitingData(): array
    {
        [$year, $month] = $this->parsePeriod();

        $base = BillingAlert::where('period_year', $year)->where('period_month', $month);

        $activeStatuses = [BillingAlert::STATUS_OPEN, BillingAlert::STATUS_IN_REVIEW];

        return [
            'critical_open'    => (clone $base)->where('severity', 'critical')->whereIn('status', $activeStatuses)->count(),
            'high_open'        => (clone $base)->where('severity', 'high')->whereIn('status', $activeStatuses)->count(),
            'confirmed_open'   => (clone $base)->where('status', BillingAlert::STATUS_CONFIRMED)->count(),
            'blocker'          => (clone $base)->where('alert_type', 'monthly_close_blocker')->whereIn('status', $activeStatuses)->exists(),
            'total_period'     => (clone $base)->count(),
            'resolved_period'  => (clone $base)->where('status', BillingAlert::STATUS_RESOLVED)->count(),
        ];
    }

    /**
     * Project / relation / insight context for all alerts currently visible.
     * Loads context for ALL active non-dismissed alerts — 4 whereIn queries, zero N+1.
     */
    public function getProjectContext(): array
    {
        $alertRows = BillingAlert::whereNotIn('status', [BillingAlert::STATUS_RESOLVED, BillingAlert::STATUS_DISMISSED])
            ->whereNotNull('project_id')
            ->get(['project_id', 'relation_id']);

        // Also include period-filtered alerts that may already be dismissed/resolved
        [$year, $month] = $this->parsePeriod();
        $periodRows = BillingAlert::where('period_year', $year)
            ->where('period_month', $month)
            ->whereNotNull('project_id')
            ->get(['project_id', 'relation_id']);

        $allRows = $alertRows->merge($periodRows);

        $projectIds  = $allRows->pluck('project_id')->filter()->unique()->values()->all();
        $alertRelIds = $allRows->pluck('relation_id')->filter()->unique()->values()->all();

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
        Notification::make()->title(__('intelligence::billing.alert_in_review'))->success()->send();
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
            ->title(__('intelligence::billing.notifications.confirmed_title'))
            ->body(__('intelligence::billing.notifications.confirmed_body'))
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
            ->title(__('intelligence::billing.notifications.dismissed_title'))
            ->body(__('intelligence::billing.notifications.dismissed_body'))
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
            ->title(__('intelligence::billing.notifications.resolved_title'))
            ->body(__('intelligence::billing.notifications.resolved_body'))
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
        Notification::make()->title(__('intelligence::billing.alert_reopened'))->warning()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run_guardian')
                ->label(__('intelligence::billing.actions.run_guardian'))
                ->icon('heroicon-o-play')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('intelligence::billing.actions.run_guardian_heading'))
                ->modalDescription(__('intelligence::billing.actions.run_guardian_desc'))
                ->action(function () {
                    [$year, $month] = $this->parsePeriod();
                    $guardian = app(MonthlyBillingGuardianService::class);
                    $report   = $guardian->analyzeMonth($year, $month);

                    Notification::make()
                        ->title(__('intelligence::billing.notifications.guardian_title', ['count' => $report->totalDetected]))
                        ->body(__('intelligence::billing.notifications.guardian_body', [
                            'created' => $report->created,
                            'updated' => $report->updated,
                            'skipped' => $report->skipped,
                        ]))
                        ->success()
                        ->send();
                }),
        ];
    }

    // -------------------------------------------------------------------------
    // Detail modal (lazy load by PK — no N+1)
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

    private function parsePeriod(): array
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $this->period, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        $now = Carbon::now('Europe/Brussels');

        return [$now->year, $now->month];
    }
}
