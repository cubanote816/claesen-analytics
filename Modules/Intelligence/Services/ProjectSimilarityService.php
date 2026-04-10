<?php

namespace Modules\Intelligence\Services;

use Modules\Intelligence\Models\Mirror\MirrorProject;
use Modules\Intelligence\Models\Mirror\MirrorCost;
use Modules\Intelligence\Models\Mirror\MirrorLabor;
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
        $isHighScale = in_array('stadion', $keywords) || in_array('arena', $keywords);
        
        // 1. Technical keyword matches (Weight: 20 each)
        if (!empty($keywords)) {
            foreach ($keywords as $word) {
                // Stadium/Arena matched specifically get even more weight if intended
                $weight = ($word === 'stadion' || $word === 'arena') ? 50 : 20;
                $scoringParts[] = "CASE WHEN name LIKE '%{$word}%' THEN {$weight} ELSE 0 END";
            }
        } else {
            $scoringParts[] = "0";
        }

        // 2. Bidirectional Scale Matching (Crucial to avoid "Stadion vs Local Pitch" mismatch)
        if ($isHighScale) {
            // If searching for a stadium, prioritize stadiums and penalize generic small sport lights
            $scoringParts[] = "CASE WHEN name LIKE '%Arena%' OR name LIKE '%Stadion%' THEN 500 ELSE -200 END";
        } else {
            // If NOT searching for a stadium, heavily penalize stadium projects to avoid scale hallucination
            $scoringParts[] = "CASE WHEN name LIKE '%Arena%' OR name LIKE '%Stadion%' THEN -500 ELSE 0 END";
        }

        // 3. Noise suppression (Maintenance/Fluvius)
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
        $insights = \Modules\Intelligence\Models\ProjectInsight::whereIn('project_id', $projectIds)
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
            ->join('intelligence_mirror_employees', 'intelligence_mirror_labor.employee_id', '=', 'intelligence_mirror_employees.id')
            ->select(
                'intelligence_mirror_employees.name',
                DB::raw('SUM(hours) as total_hours')
            )
            ->groupBy('intelligence_mirror_employees.id', 'intelligence_mirror_employees.name')
            ->orderByDesc('total_hours')
            ->limit(3)
            ->get();
    }
}
