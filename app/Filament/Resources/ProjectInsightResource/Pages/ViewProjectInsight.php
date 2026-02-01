<?php

namespace App\Filament\Resources\ProjectInsightResource\Pages;

use App\Filament\Resources\ProjectInsightResource;
use Filament\Resources\Pages\ViewRecord;

class ViewProjectInsight extends ViewRecord
{
    protected static string $resource = ProjectInsightResource::class;

    protected function resolveRecord($key): \Illuminate\Database\Eloquent\Model
    {
        $record = \App\Models\ProjectInsight::where('project_id', $key)->first();

        if (!$record) {
            // Check if it exists in legacy before creating
            $legacyExists = \App\Models\Cafca\Project::where('id', $key)->exists();

            if ($legacyExists) {
                $record = \App\Models\ProjectInsight::create([
                    'project_id' => $key,
                    'insight_type' => 'audit_budget',
                ]);
            }
        }

        return $record ?? abort(404);
    }
}
