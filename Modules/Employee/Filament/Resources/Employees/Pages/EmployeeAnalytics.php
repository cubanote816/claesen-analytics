<?php

namespace Modules\Employee\Filament\Resources\Employees\Pages;

use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Modules\Cafca\Filament\Resources\Employees\Schemas\EmployeeAnalyticsInfolist;
use Modules\Employee\Filament\Resources\EmployeeResource;
use Modules\Performance\Services\EmployeePerformanceService;

class EmployeeAnalytics extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getTitle(): \Illuminate\Contracts\Support\Htmlable|string
    {
        return app()->getLocale() === 'nl' ? 'IA Prestaties' : 'AI Performance';
    }

    public function getHeading(): \Illuminate\Contracts\Support\Htmlable|string
    {
        return app()->getLocale() === 'nl' ? 'IA Prestaties' : 'AI Performance';
    }

    public function getSubheading(): \Illuminate\Contracts\Support\Htmlable|string|null
    {
        $record  = $this->getRecord();
        $insight = $record->insight;
        $isNl    = app()->getLocale() === 'nl';

        if ($insight?->last_audited_at) {
            $label = $isNl ? 'Laatste analyse' : 'Last analysis';
            return $label . ': ' . $insight->last_audited_at->format('d M Y, H:i');
        }

        return $isNl ? 'Nog geen AI-analyse beschikbaar' : 'No AI analysis available yet';
    }

    public static function getNavigationLabel(): string
    {
        return app()->getLocale() === 'nl' ? 'IA Prestaties' : 'AI Performance';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-cpu-chip';
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
                ->outlined()
                ->requiresConfirmation()
                ->action(function () {
                    $employee = $this->getRecord();
                    $insight  = $employee->insight;
                    $isNl     = app()->getLocale() === 'nl';

                    if ($insight && $insight->last_audited_at && $insight->last_audited_at->gt(now()->subDay())) {
                        \Filament\Notifications\Notification::make()
                            ->title($isNl ? 'Limiet Bereikt' : 'Limit Reached')
                            ->warning()
                            ->body($isNl
                                ? 'De AI-analyse kan slechts eenmaal per 24 uur worden vernieuwd.'
                                : 'AI analysis can only be refreshed once every 24 hours.')
                            ->send();
                        return;
                    }

                    try {
                        $service = app(\Modules\Performance\Services\TechnicianAnalysisService::class);
                        $service->analyzeTechnician($employee->id, $employee->name);

                        \Filament\Notifications\Notification::make()
                            ->title($isNl ? 'IA Analyse Voltooid' : 'AI Analysis Completed')
                            ->success()
                            ->body($isNl
                                ? 'De prestatie-insights zijn succesvol bijgewerkt.'
                                : 'Performance insights have been successfully updated.')
                            ->send();

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
                ->label(app()->getLocale() === 'nl' ? 'Rapport Downloaden' : 'Download Report')
                ->icon('heroicon-m-document-arrow-down')
                ->color('primary')
                ->outlined()
                ->action(fn() => $this->exportToPdf()),
        ];
    }

    public function exportToPdf()
    {
        ini_set('memory_limit', '256M');

        $employee = $this->getRecord();
        $service  = app(EmployeePerformanceService::class);

        $data = [
            'employee'     => $employee,
            'weekly'       => $service->getWeeklyStats($employee, now()),
            'monthly'      => $service->getMonthlyStats($employee, now()),
            'profile'      => $service->getPerformanceProfile($employee),
            'ranking'      => $service->getComparativeRanking($employee),
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

        return $user && ($user->hasRole('super_admin') || $user->hasRole('admin'));
    }
}
