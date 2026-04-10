<?php

namespace Modules\Analytics\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Models\Mirror\MirrorMaterial;
use Modules\Analytics\Models\Mirror\MirrorProject;
use Illuminate\Support\Facades\Log;

class MapWarehouseCategoriesCommand extends Command
{
    protected $signature = 'analytics:map-warehouse-categories {--limit=1000 : Limit items to process per run}';
    protected $description = 'Categorizes mirrored materials based on historical project usage peaks.';

    public function handle()
    {
        $this->info("Starting Deep Warehouse Intelligent Mapping...");

        // 1. Evidence-Based Mapping (Highest Priority: Historical Usage)
        $this->info("Step 1: Analyzing historical evidence...");
        $stats = DB::table('analytics_mirror_costs as c')
            ->join('analytics_mirror_projects as p', 'c.project_id', '=', 'p.id')
            ->whereNotNull('c.art_id')
            ->select('c.art_id', 'p.category', DB::raw('COUNT(*) as use_count'))
            ->groupBy('c.art_id', 'p.category')
            ->orderByDesc('use_count')
            ->get();

        foreach ($stats as $stat) {
            $material = MirrorMaterial::find($stat->art_id);
            if ($material) {
                $material->update([
                    'category_ai' => $stat->category,
                    'last_learned_at' => now(),
                ]);
            }
        }
        $this->info("Mapped " . $stats->count() . " items based on history.");

        // 2. Semantic Mapping (Calibrated Massive Mapping)
        $this->info("Step 2: Starting Calibrated Semantic Sweep...");
        
        $dictionaries = [
            'Sport' => [
                'projector', 'schijnwerper', 'veld', 'mast', 'arena', 'sport', 'stadion', 
                'tennis', 'voetbal', 'atletiek', 'flooding', 'luminus', 'v7', 'arena-vision'
            ],
            'Industrial' => [
                'highbay', 'lineair', 'industrie', 'magazijn', 'loods', 'hal', 
                'werkplaats', 'klok', 'armatuur', 'bell', 'industrieel', 'opslag'
            ],
            'Public Spaces' => [
                'paaltop', 'park', 'plein', 'ov', 'road', 'straat', 'wegverlichting', 
                'pad', 'fietspad', 'paal', 'top', 'kegel', 'bolder', 'straatlamp'
            ],
        ];

        // Service keywords to ignore (Anti-Damping)
        $noiseKeywords = [
            'verplaatsing', 'uurloon', 'administratie', 'keuring', 'huur', 
            'service', 'km-vergoeding', 'bestelwagen', 'betreft'
        ];

        $totalCategorized = 0;
        MirrorMaterial::where(function($q) {
                $q->whereNull('category_ai')
                  ->orWhere('category_ai', '');
            })
            ->chunk(1000, function ($materials) use ($dictionaries, $noiseKeywords, &$totalCategorized) {
                foreach ($materials as $material) {
                    $desc = strtolower($material->description);
                    
                    // Skip noise
                    foreach ($noiseKeywords as $noise) {
                        if (str_contains($desc, $noise)) {
                            continue 2;
                        }
                    }

                    $foundCategory = null;
                    foreach ($dictionaries as $category => $keywords) {
                        foreach ($keywords as $keyword) {
                            if (str_contains($desc, $keyword)) {
                                $foundCategory = $category;
                                break 2;
                            }
                        }
                    }

                    if ($foundCategory) {
                        $material->update([
                            'category_ai' => $foundCategory,
                            'last_learned_at' => now(),
                        ]);
                        $totalCategorized++;
                    }
                }
                $this->comment("Processed batch... ($totalCategorized new items found so far)");
            });

        $this->info("Success: Deep mapping complete. Total items categorized semantically: $totalCategorized");
        
        return Command::SUCCESS;
    }
}
