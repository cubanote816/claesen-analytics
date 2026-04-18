<?php

namespace Modules\Performance\Filament\Resources\ProjectInsightResource\Pages;

use Modules\Performance\Filament\Resources\ProjectInsightResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Modules\Performance\Jobs\AuditProjectJob;

class ViewProjectInsight extends ViewRecord
{
    protected static string $resource = ProjectInsightResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('ai_analysis')
                ->label(app()->getLocale() === 'nl' ? 'AI Analyse Starten' : 'Start AI Analysis')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->action(function () {
                    AuditProjectJob::dispatch($this->record->project_id);
                    
                    \Filament\Notifications\Notification::make()
                        ->title(app()->getLocale() === 'nl' ? 'Analyse Gestart' : 'Analysis Started')
                        ->body(app()->getLocale() === 'nl' 
                            ? 'De AI audit voor dit project is in de wachtrij geplaatst.' 
                            : 'The AI audit for this project has been queued.')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading(app()->getLocale() === 'nl' ? 'AI Strategische Analyse' : 'AI Strategic Analysis')
                ->modalDescription(app()->getLocale() === 'nl' 
                    ? 'Wilt u een nieuwe AI-audit uitvoeren voor dit project? Dit zal de SWOT-matrix en de efficiëntiescore bijwerken.' 
                    : 'Do you want to run a new AI audit for this project? This will update the SWOT matrix and efficiency score.')
                ->modalSubmitActionLabel(app()->getLocale() === 'nl' ? 'Start Analyse' : 'Start Analysis'),
        ];
    }

    protected function resolveRecord($key): \Illuminate\Database\Eloquent\Model
    {
        $record = \Modules\Performance\Models\ProjectInsight::where('project_id', $key)->first();

        if (!$record) {
            // Check if it exists in legacy before creating
            $legacyExists = \Modules\Cafca\Models\Project::where('id', $key)->exists();

            if ($legacyExists) {
                $record = \Modules\Performance\Models\ProjectInsight::create([
                    'project_id' => $key,
                    'insight_type' => 'audit_budget',
                ]);
            }
        }

        return $record ?? abort(404);
    }
}
