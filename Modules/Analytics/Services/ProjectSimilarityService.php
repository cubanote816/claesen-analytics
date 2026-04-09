<?php

namespace Modules\Analytics\Services;

use Modules\Analytics\Models\Mirror\MirrorProject;
use Modules\Analytics\Models\Mirror\MirrorCost;
use Modules\Analytics\Models\Mirror\MirrorLabor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectSimilarityService
{
    /**
     * Find similar historical projects and aggregate insights.
     */
    public function findSimilar(string $category, ?string $zipcode = null, int $limit = 5, string $description = ''): Collection
    {
        $query = MirrorProject::where('category', $category);

        // 1. Extract technical DNA (Keywords)
        $keywords = $this->extractKeywords($description);

        // 2. Perform Global Search (Relevance based on keywords and scale)
        // We prioritize Global Matches over Local Non-matches
        $results = $this->applyRanking($query, $keywords, $zipcode)
            ->limit($limit)
            ->get();

        return $this->mapResults($results);
    }

    private function extractKeywords(string $text): array
    {
        $text = strtolower($text);
        // Define high-scale anchor keywords
        $candidates = [
            'stadion', 'arena', 'fc', 'voetbal', 'hockey', 'sport', // Sport High Scale
            'highbay', 'magazijn', 'loods', 'industrie',          // Industrial Scale
            'renovatie', 'vervanging', 'led', 'mast'               // Technical
        ];
        
        $found = [];
        foreach ($candidates as $cand) {
            if (str_contains($text, $cand)) $found[] = $cand;
        }
        return $found;
    }

    private function applyRanking($query, array $keywords, ?string $zipcode = null)
    {
        $scoringParts = [];
        
        // Technical keyword matches (Weight: 10 each)
        if (!empty($keywords)) {
            foreach ($keywords as $word) {
                $scoringParts[] = "CASE WHEN name LIKE '%{$word}%' THEN 20 ELSE 0 END";
            }
        } else {
            $scoringParts[] = "0";
        }

        // Proximity matches (Weight: 5)
        if ($zipcode) {
            $zipPrefix = substr(preg_replace('/[^0-9]/', '', $zipcode), 0, 2);
            if ($zipPrefix) {
                $scoringParts[] = "CASE WHEN zipcode LIKE '{$zipPrefix}%' THEN 5 ELSE 0 END";
            }
        }

        // Scale recognition (Names with 'Arena' or 'Stadion' get massive boost for technical intent)
        $scoringParts[] = "CASE WHEN name LIKE '%Arena%' OR name LIKE '%Stadion%' THEN 50 ELSE 0 END";

        // Noise suppression (Fluvius/Maintenance is heavily penalized for specific technical requests)
        $noisePenalty = "CASE WHEN name LIKE '%Fluvius%' OR name LIKE '%S.O.%' OR name LIKE '%Onderhoud%' THEN -100 ELSE 0 END";

        $finalScoring = "(" . implode(' + ', $scoringParts) . " + {$noisePenalty})";

        return $query->select('*')
            ->selectRaw("{$finalScoring} as relevance_score")
            ->orderByDesc('relevance_score')
            ->latest('last_modified_at');
    }

    private function hasHighRelevance(Collection $results, array $keywords): bool
    {
        if (empty($keywords)) return true;
        return $results->first()?->relevance_score > 0;
    }

    private function mapResults(Collection $projects): Collection
    {
        $projectIds = $projects->pluck('id')->toArray();
        
        // Fetch insights (Lessons Learned) for these projects
        $insights = \Modules\Analytics\Models\ProjectInsight::whereIn('project_id', $projectIds)
            ->get(['project_id', 'efficiency_score', 'critical_leak', 'golden_rule'])
            ->keyBy('project_id');

        return $projects->map(function ($project) use ($insights) {
            $insight = $insights->get($project->id);

            return [
                'id' => $project->id,
                'name' => $project->name,
                'year' => $project->last_modified_at?->year,
                'city' => $project->city,
                'zipcode' => $project->zipcode,
                'financials' => $this->getAggregatedFinancials($project->id),
                'technicians' => $this->getTopTechnicians($project->id),
                'lessons' => $insight ? [
                    'efficiency' => $insight->efficiency_score,
                    'pitfall' => $insight->critical_leak,
                    'golden_rule' => $insight->golden_rule
                ] : null,
            ];
        });
    }

    /**
     * Get aggregated costs from the mirror.
     */
    private function getAggregatedFinancials(string $projectId): array
    {
        $costs = MirrorCost::where('project_id', $projectId)
            ->select('type', DB::raw('SUM(cost_price * quantity) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            'material' => $costs->get('M', 0),
            'labor' => $costs->get('A', 0),
            'equipment' => $costs->get('M', 0), // Adjusting based on naming convention
            'subcontracting' => $costs->get('O', 0),
        ];
    }

    /**
     * Get top technicians by effective hours / total hours on this project.
     */
    private function getTopTechnicians(string $projectId): Collection
    {
        return MirrorLabor::where('project_id', $projectId)
            ->join('analytics_mirror_employees', 'analytics_mirror_labor.employee_id', '=', 'analytics_mirror_employees.id')
            ->select(
                'analytics_mirror_employees.name',
                DB::raw('SUM(hours) as total_hours')
            )
            ->groupBy('analytics_mirror_employees.id', 'analytics_mirror_employees.name')
            ->orderByDesc('total_hours')
            ->limit(3)
            ->get();
    }
}
