<?php

namespace Modules\Intelligence\Filament\Pages;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Intelligence\Models\BillingAlert;
use Modules\Intelligence\Services\MonthlyBillingGuardianService;

class MonthlyBillingControlPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $slug = 'billing-control';
    protected static ?int $navigationSort = 30;

    protected string $view = 'intelligence::filament.pages.billing-control';

    public string $period = '';
    public string $activeTab = 'all';

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
        Notification::make()->title('Alert bevestigd.')->success()->send();
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
        Notification::make()->title('Alert gesloten (afgewezen).')->info()->send();
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
        Notification::make()->title('Alert opgelost.')->success()->send();
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
                        ? 'Dit detecteert facturatieafwijkingen voor de geselecteerde periode en slaat de resultaten op.'
                        : 'This will detect billing anomalies for the selected period and persist the results.'
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

    private function parsePeriod(): array
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $this->period, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        $now = Carbon::now('Europe/Brussels');

        return [$now->year, $now->month];
    }
}
