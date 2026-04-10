<?php

namespace Modules\Analytics\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Modules\Analytics\Services\TechnicianAnalysisService;
use Modules\Cafca\Models\Employee;

class EmployeeInsight extends Page implements HasForms
{
    use InteractsWithForms;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-users';
    }

    protected string $view = 'analytics::filament.pages.employee-insight';
    
    public ?array $data = [];
    public ?array $analysisResult = null;
    public bool $isAnalyzing = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.workforce_performance');
    }

    public static function getNavigationLabel(): string
    {
        return app()->getLocale() === 'nl' ? 'Technici Inzichten' : 'HR Technician Insights';
    }

    public function getTitle(): string
    {
        return app()->getLocale() === 'nl' ? 'Technici Inzichten (IA)' : 'Technician Insights (AI)';
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('employee_id')
                    ->label(app()->getLocale() === 'nl' ? 'Selecteer Technicus' : 'Select Technician')
                    ->options(Employee::limit(100)->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function analyze(): void
    {
        $this->isAnalyzing = true;
        
        $employeeId = $this->data['employee_id'] ?? null;
        if (!$employeeId) {
            $this->isAnalyzing = false;
            return;
        }

        $name = Employee::find($employeeId)?->name ?? 'Unknown';

        $service = app(TechnicianAnalysisService::class);
        $this->analysisResult = $service->analyzeTechnician($employeeId, $name);
        
        $this->isAnalyzing = false;
    }
}
