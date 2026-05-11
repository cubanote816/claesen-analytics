<?php

declare(strict_types=1);

namespace Modules\Safety\Console;

use Illuminate\Console\Command;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Safety\Models\Inspection;
use Modules\Core\Models\User;
use Filament\Notifications\Notification;
use Filament\Actions\Action;

class CheckSafetyComplianceCommand extends Command
{
    protected $signature = 'safety:check-compliance';
    protected $description = 'Checks for projects missing their monthly safety inspection and notifies admins.';

    public function handle(): void
    {
        $this->info('Checking safety compliance...');

        // Solo proyectos activos
        $activeProjects = MirrorProject::where('fl_active', true)->get();
        $missingInspections = [];

        foreach ($activeProjects as $project) {
            $latestInspection = Inspection::where('project_id', $project->id)
                ->latest('completed_at')
                ->first();

            if (!$latestInspection || $latestInspection->completed_at->diffInDays(now()) > 30) {
                $missingInspections[] = $project;
            }
        }

        if (count($missingInspections) > 0) {
            $this->warn(count($missingInspections) . ' projects are missing inspections.');

            $admins = User::role('super_admin')->get();
            
            if ($admins->count() > 0) {
                Notification::make()
                    ->title('Veiligheid Alert: Inspecties over tijd')
                    ->warning()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->body(sprintf(
                        "Er zijn **%d** actieve projecten die al meer dan 30 dagen geen werkplekinspectie hebben gehad.",
                        count($missingInspections)
                    ))
                    ->actions([
                        Action::make('view_inspections')
                            ->label('Bekijk Inspecties')
                            ->url(\Modules\Safety\Filament\Resources\InspectionResource::getUrl('index'))
                    ])
                    ->sendToDatabase($admins);
            }
        } else {
            $this->info('All projects are compliant.');
        }
    }
}
