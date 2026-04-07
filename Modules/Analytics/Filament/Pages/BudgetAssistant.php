<?php

namespace Modules\Analytics\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Modules\Analytics\Services\BudgetAssistantService;

class BudgetAssistant extends Page implements HasForms
{
    use InteractsWithForms;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-calculator';
    }
    protected string $view = 'analytics::filament.pages.budget-assistant';
    
    public ?array $data = [];
    public ?string $analysisResult = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.growth_acquisition');
    }

    public static function getNavigationLabel(): string
    {
        return app()->getLocale() === 'nl' ? 'Budget Assistent' : 'Budget Assistant';
    }

    public function getTitle(): string
    {
        return app()->getLocale() === 'nl' ? 'Budget Assistent (IA Simulatie)' : 'Budget Assistant (AI Simulation)';
    }

    public function form(Form $form): Form
    {
        // V5 Strictly applying Filament\Schemas\Schema
        return $form
            ->schema([
                Schema::make([
                    Select::make('category')
                        ->label(app()->getLocale() === 'nl' ? 'Project Categorie' : 'Project Category')
                        ->options([
                            'Sportverlichting' => 'Sportverlichting (Infraestructura Deportiva)',
                            'Industrie' => 'Industrie (Iluminación Industrial)',
                            'Openbare Verlichting' => 'Openbare Verlichting (Monumental y Pública)',
                            'Masten' => 'Masten (Mástiles)',
                            'Algemeen' => 'Algemeen',
                        ])
                        ->required(),
                    TextInput::make('zipcode')
                        ->label(app()->getLocale() === 'nl' ? 'Postcode (Zipcode)' : 'Zipcode')
                        ->numeric()
                        ->required()
                        ->maxLength(4),
                ])
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $service = app(BudgetAssistantService::class);
        $data = $this->form->getState();
        $this->analysisResult = $service->simulateOffer($data, app()->getLocale());
    }
}
