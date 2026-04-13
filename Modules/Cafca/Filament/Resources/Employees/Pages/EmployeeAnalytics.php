<?php

namespace Modules\Cafca\Filament\Resources\Employees\Pages;

use Modules\Cafca\Filament\Resources\EmployeeResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Modules\Cafca\Filament\Resources\Employees\Schemas\EmployeeAnalyticsInfolist;
use Filament\Schemas\Schema;
use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Performance\Services\EmployeePerformanceService;

class EmployeeAnalytics extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    protected static ?string $title = 'AI Performance Analytics';

    public static function getNavigationLabel(): string
    {
        return app()->getLocale() === 'nl' ? 'IA Analitica' : 'AI Performance';
    }

    public function infolist(Schema $schema): Schema
    {
        return EmployeeAnalyticsInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshAi')
                ->label(app()->getLocale() === 'nl' ? 'IA Analyse Herberekenen' : 'Recalculate AI Analysis')
                ->icon('heroicon-m-sparkles')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $employee = $this->getRecord();
                    $insight = $employee->insight;

                    // Throttle: 24 hours
                    if ($insight && $insight->last_audited_at && $insight->last_audited_at->gt(now()->subDay())) {
                        \Filament\Notifications\Notification::make()
                            ->title(app()->getLocale() === 'nl' ? 'Limiet Bereikt' : 'Limit Reached')
                            ->warning()
                            ->body(app()->getLocale() === 'nl' 
                                ? 'De AI-analyse kan slechts eenmaal per 24 uur worden vernieuwd.' 
                                : 'AI analysis can only be refreshed once every 24 hours.')
                            ->send();
                        return;
                    }

                    try {
                        $service = app(\Modules\Performance\Services\TechnicianAnalysisService::class);
                        $service->analyzeTechnician($employee->id, $employee->name);

                        \Filament\Notifications\Notification::make()
                            ->title(app()->getLocale() === 'nl' ? 'IA Analyse Voltooid' : 'AI Analysis Completed')
                            ->success()
                            ->body(app()->getLocale() === 'nl' 
                                ? 'De prestatie-insights zijn succesvol bijgewerkt.' 
                                : 'Performance insights have been successfully updated.')
                            ->send();
                        
                        // Refresh the page to show new data
                        return redirect(request()->header('Referer'));

                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Error')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Action::make('downloadPdf')
                ->label(app()->getLocale() === 'nl' ? 'Rapport Downloaden (PDF)' : 'Download Report (PDF)')
                ->icon('heroicon-m-document-arrow-down')
                ->color('primary')
                ->action(fn () => $this->exportToPdf()),
        ];
    }

    public function exportToPdf()
    {
        // Increase memory limit for PDF generation if needed
        ini_set('memory_limit', '256M');

        $employee = $this->getRecord();
        $service = app(EmployeePerformanceService::class);
        
        $data = [
            'employee' => $employee,
            'weekly' => $service->getWeeklyStats($employee, now()),
            'monthly' => $service->getMonthlyStats($employee, now()),
            'profile' => $service->getPerformanceProfile($employee),
            'ranking' => $service->getComparativeRanking($employee),
            'generated_at' => now()->format('d-m-Y H:i'),
        ];

        $pdf = Pdf::loadView('performance::pdf.performance-report', $data);
        
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, "Performance_Report_{$employee->name}.pdf");
    }

    public static function canAccess(array $parameters = []): bool
    {
        /** @var \Modules\Core\Models\User $user */
        $user = auth()->user();
        
        // Ensure user is admin or super admin
        return $user && ($user->hasRole('super_admin') || $user->hasRole('admin'));
    }
}
