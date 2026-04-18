<?php

namespace Modules\Performance\Filament\Resources\ProjectResource\Pages;

use Modules\Cafca\Models\Project;
use Modules\Performance\Filament\Resources\ProjectResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(app()->getLocale() === 'nl' ? 'Alles' : 'All'),
            
            'active' => Tab::make(app()->getLocale() === 'nl' ? 'Actief' : 'Active')
                ->badge(Project::query()->where('fl_active', true)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('fl_active', true)),

            'recent_labor' => Tab::make(app()->getLocale() === 'nl' ? 'Recente Arbeid' : 'Recent Labor')
                ->badge(Project::query()->whereHas('labor', fn ($q) => 
                    $q->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
                )->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('labor', fn ($q) => 
                    $q->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
                )),

            'pending_finance' => Tab::make(app()->getLocale() === 'nl' ? 'Facturatie Wachtend' : 'Pending Collections')
                ->badge(Project::query()->whereHas('invoices', function ($q) {
                    return $q->select(DB::raw('project_id'))
                        ->groupBy('project_id')
                        ->havingRaw('SUM(total_price) - SUM(total_paid) > 0.05');
                })->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('invoices', function ($q) {
                    return $q->select(DB::raw('project_id'))
                        ->groupBy('project_id')
                        ->havingRaw('SUM(total_price) - SUM(total_paid) > 0.05');
                })),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'active';
    }
}
