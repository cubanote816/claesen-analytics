<?php

namespace Modules\Analytics\Filament\Resources\ProjectInsightResource\Pages;

use Modules\Analytics\Filament\Resources\ProjectInsightResource;
use Filament\Resources\Pages\ViewRecord;

class ViewProjectInsight extends ViewRecord
{
    protected static string $resource = ProjectInsightResource::class;

    protected function resolveRecord($key): \Illuminate\Database\Eloquent\Model
    {
        $record = \Modules\Analytics\Models\ProjectInsight::where('project_id', $key)->first();

        if (!$record) {
            // Check if it exists in legacy before creating
            $legacyExists = \Modules\Cafca\Models\Project::where('id', $key)->exists();

            if ($legacyExists) {
                $record = \Modules\Analytics\Models\ProjectInsight::create([
                    'project_id' => $key,
                    'insight_type' => 'audit_budget',
                ]);
            }
        }

        return $record ?? abort(404);
    }
}
