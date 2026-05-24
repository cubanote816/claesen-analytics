<?php

declare(strict_types=1);

namespace Modules\Safety\Console;

use Illuminate\Console\Command;
use Modules\Core\Models\User;
use Modules\Safety\Services\ComplianceService;
use Filament\Notifications\Notification;
use Filament\Actions\Action;

class CheckSafetyComplianceCommand extends Command
{
    protected $signature = 'safety:check-compliance';
    protected $description = 'Checks for projects missing their monthly safety inspection and notifies admins.';

    public function __construct(private readonly ComplianceService $compliance)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->info('Checking safety compliance...');

        $missing = $this->compliance->getMissingInspections();

        if ($missing->isNotEmpty()) {
            $this->warn($missing->count() . ' projects are missing inspections.');

            $admins = User::role('super_admin')->get();

            if ($admins->isNotEmpty()) {
                Notification::make()
                    ->title('Veiligheid Alert: Inspecties over tijd')
                    ->warning()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->body(sprintf(
                        "Er zijn **%d** actieve projecten die al meer dan %d dagen geen werkplekinspectie hebben gehad.",
                        $missing->count(),
                        config('safety.compliance_days')
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
