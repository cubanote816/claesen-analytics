<?php

namespace Modules\Employee\Filament\Resources\Employees\Pages;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Modules\Employee\Filament\Resources\EmployeeResource;
use Modules\Employee\Services\EmployeeTimeService;

class EmployeeHoursPage extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    protected string $view = 'employee::filament.resources.employees.pages.employee-hours-page';

    public string $month = '';
    public ?array $data  = null;
    public ?string $errorMessage = null;

    public static function getNavigationLabel(): string
    {
        return app()->getLocale() === 'nl' ? 'Uren' : 'Hours';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clock';
    }

    public function getTitle(): \Illuminate\Contracts\Support\Htmlable|string
    {
        $isNl = app()->getLocale() === 'nl';
        return $isNl ? 'Uren Overzicht' : 'Hours Overview';
    }

    public function getHeading(): \Illuminate\Contracts\Support\Htmlable|string
    {
        return $this->record->name;
    }

    public function getSubheading(): \Illuminate\Contracts\Support\Htmlable|string|null
    {
        if (!$this->month) {
            return null;
        }
        $date = Carbon::createFromFormat('Y-m', $this->month);
        $isNl = app()->getLocale() === 'nl';
        return $date ? $date->locale($isNl ? 'nl' : 'en')->isoFormat('MMMM Y') : $this->month;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->month = request()->query('month', Carbon::now()->format('Y-m'));
        $this->loadData();
    }

    public function updatedMonth(): void
    {
        $this->loadData();
    }

    private function loadData(): void
    {
        try {
            $service    = app(EmployeeTimeService::class);
            $this->data = $service->getMonthWeeksStats((string) $this->record->id, $this->month);
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
            $this->data         = null;
        }
    }

    protected function getHeaderActions(): array
    {
        $current = Carbon::createFromFormat('Y-m', $this->month);
        $prev    = $current?->copy()->subMonth()->format('Y-m') ?? '';
        $next    = $current?->copy()->addMonth()->format('Y-m') ?? '';

        return [
            Action::make('prev_month')
                ->label($current?->copy()->subMonth()->locale(app()->getLocale() === 'nl' ? 'nl' : 'en')->isoFormat('MMM Y') ?? '')
                ->icon('heroicon-o-chevron-left')
                ->color('gray')
                ->outlined()
                ->url(static::getUrl(['record' => $this->record, 'month' => $prev])),

            Action::make('next_month')
                ->label($current?->copy()->addMonth()->locale(app()->getLocale() === 'nl' ? 'nl' : 'en')->isoFormat('MMM Y') ?? '')
                ->icon('heroicon-o-chevron-right')
                ->iconPosition(\Filament\Support\Enums\IconPosition::After)
                ->color('gray')
                ->outlined()
                ->url(static::getUrl(['record' => $this->record, 'month' => $next])),
        ];
    }
}
