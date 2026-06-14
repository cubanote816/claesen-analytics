<?php

namespace Modules\Intelligence\Filament\Pages;

use Filament\Pages\Page;
use Modules\Intelligence\Models\BillingAlert;
use Modules\Performance\Filament\Resources\ProjectInsightResource;
use Modules\Performance\Models\Mirror\MirrorCost;
use Modules\Performance\Models\Mirror\MirrorInvoice;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Performance\Models\Mirror\MirrorRelation;
use Modules\Performance\Models\ProjectInsight;

/**
 * BI-PROJ-01 rationale: ProjectInsightResource (Performance module) is NOT extended here.
 * That resource reads from SQL Server (Cafca\Models\Project, ReadOnly) and generates AI
 * narratives via Gemini — it is an analytical/insight layer, not an operational view.
 * This page is read-only operational detail (mirror tables, billing alerts, invoice state)
 * and belongs in the Intelligence module alongside the Guardian commands that populate it.
 *
 * Invoice note: MirrorInvoice stores total_price_vat_excl + fl_paid (binary).
 * There is no total_paid column — "paid" means the full invoice is settled.
 * Partial payment detail is available only through BillingAlert.amount_open (post PR #6).
 */
class ProjectIntelligenceDetail extends Page
{
    protected static ?string $slug = 'project-detail/{projectId}';
    protected static bool $shouldRegisterNavigation = false;
    protected string $view = 'intelligence::filament.pages.project-intelligence-detail';

    public string $projectId   = '';
    public string $projectName = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'project_manager']) ?? false;
    }

    public function mount(string $projectId): void
    {
        $this->projectId = trim($projectId);
        $project = MirrorProject::find($this->projectId);
        if (!$project) {
            abort(404);
        }
        $this->projectName = $project->name ?? $this->projectId;
    }

    public function getTitle(): string
    {
        return $this->projectName ?: $this->projectId;
    }

    /**
     * Load all page data in one pass — 6 queries max, zero N+1.
     */
    public function getPageData(): array
    {
        $project  = MirrorProject::find($this->projectId);
        $relation = $project?->relation_id
            ? MirrorRelation::find($project->relation_id)
            : null;

        $invoices = MirrorInvoice::where('project_id', $this->projectId)
            ->orderByDesc('date')
            ->get();

        $costs = MirrorCost::where('project_id', $this->projectId)
            ->orderByDesc('date')
            ->get();

        $alerts = BillingAlert::where('project_id', $this->projectId)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderByRaw("FIELD(severity,'critical','high','medium','low')")
            ->get();

        $insightUrl = null;
        if (ProjectInsight::where('project_id', $this->projectId)->exists()) {
            $insightUrl = ProjectInsightResource::getUrl('view', ['record' => $this->projectId]);
        }

        // Invoice aggregates — MirrorInvoice has no total_paid; fl_paid is binary
        $totalInvoiced = $invoices->sum(fn($i) => (float) $i->total_price_vat_excl);
        $totalPaid     = $invoices->where('fl_paid', true)->sum(fn($i) => (float) $i->total_price_vat_excl);
        $openBalance   = $invoices->where('fl_paid', false)->sum(fn($i) => (float) $i->total_price_vat_excl);
        $overdueCount  = $invoices->filter(
            fn($i) => !$i->fl_paid && $i->date_expiration?->isPast()
        )->count();
        $creditNotes   = $invoices->filter(fn($i) => str_starts_with((string) $i->id, 'CN'));

        // Cost aggregates
        $totalCost    = $costs->sum(fn($c) => (float) $c->cost_price * (float) $c->quantity);
        $unbilledCost = $costs->where('invoiced', false)
            ->sum(fn($c) => (float) $c->cost_price * (float) $c->quantity);
        $costsByType  = $costs->groupBy('type')->map(fn($g, $type) => [
            'type'     => $type,
            'count'    => $g->count(),
            'amount'   => $g->sum(fn($c) => (float) $c->cost_price * (float) $c->quantity),
            'unbilled' => $g->where('invoiced', false)
                ->sum(fn($c) => (float) $c->cost_price * (float) $c->quantity),
        ])->sortByDesc('amount');

        return compact(
            'project', 'relation',
            'invoices', 'costs', 'alerts', 'insightUrl', 'creditNotes',
            'totalInvoiced', 'totalPaid', 'openBalance', 'overdueCount',
            'totalCost', 'unbilledCost', 'costsByType',
        );
    }

    /**
     * Static helper for building the URL from outside this page.
     */
    public static function getProjectUrl(string $projectId): string
    {
        return url('project-detail/' . rawurlencode(trim($projectId)));
    }
}
