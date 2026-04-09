<?php

namespace Modules\Analytics\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Slider;
use Filament\Actions\Action;
use Modules\Analytics\Services\BudgetAssistantService;

class OfferSimulator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $slug = 'offer-simulator';
    protected static ?int $navigationSort = 1;

    protected string $view = 'analytics::filament.pages.offer-simulator';

    public ?array $data = [];
    public ?array $results = null;
    public bool $isSimulating = false;

    public function mount(): void
    {
        $this->form->fill([
            'complexity' => 1.0,
        ]);
    }

    public static function getNavigationGroup(): ?string
    {
        return app()->getLocale() === 'nl' ? 'Intelligentie Hub' : 'Intelligence Hub';
    }

    public static function getNavigationLabel(): string
    {
        return app()->getLocale() === 'nl' ? 'Offerte Simulator' : 'Offer Simulator';
    }

    public function getTitle(): string
    {
        return app()->getLocale() === 'nl' ? 'AI Offerte Simulator' : 'AI Offer Simulator';
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make(app()->getLocale() === 'nl' ? 'Project Details' : 'Project Details')
                    ->schema([
                        Textarea::make('description')
                            ->label(app()->getLocale() === 'nl' ? 'Projectbeschrijving' : 'Project Description')
                            ->placeholder(app()->getLocale() === 'nl' ? 'Beschrijf het project (bijv. 4 masten van 15m in Brugge...)' : 'Describe the project...')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, callable $set) {
                                if (blank($state)) {
                                    return;
                                }
                                // Extract first 4 or 5-digit number as potential zipcode
                                if (preg_match('/\b(\d{4,5})\b/', $state, $matches)) {
                                    $set('zipcode', $matches[1]);
                                }
                            }),
                        
                        Select::make('category')
                            ->label(app()->getLocale() === 'nl' ? 'Categorie' : 'Category')
                            ->options([
                                'Sportverlichting' => 'Sportverlichting',
                                'Industrie' => 'Industrie',
                                'Openbare Verlichting' => 'Openbare Verlichting',
                                'Masten' => 'Masten',
                            ])
                            ->required(),

                        TextInput::make('zipcode')
                            ->label(app()->getLocale() === 'nl' ? 'Postcode' : 'Zipcode')
                            ->numeric()
                            ->required()
                            ->maxLength(5),

                        Slider::make('complexity')
                            ->label(app()->getLocale() === 'nl' ? 'Moeilijkheidsgraad (Factor)' : 'Complexity Factor')
                            ->helperText(app()->getLocale() === 'nl' ? 'AI suggereert dit op basis van de tekst, maar u kunt dit aanpassen.' : 'AI suggests this, but you can override.')
                            ->minValue(0.5)
                            ->maxValue(2.5)
                            ->step(0.1)
                            ->default(1.0),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('simulate')
                ->label(app()->getLocale() === 'nl' ? 'Simuleer Offerte' : 'Simulate Offer')
                ->submit('simulate')
                ->color('primary')
                ->icon('heroicon-m-sparkles'),
        ];
    }

    public function simulate(): void
    {
        $this->results = null; // Clear previous state to show loading/reset
        $this->isSimulating = true;
        
        $service = app(BudgetAssistantService::class);
        $formData = $this->form->getState();

        // Perform simulation
        $this->results = $service->simulate(
            $formData['description'],
            $formData['category'],
            $formData['zipcode'],
            $formData['complexity'],
            app()->getLocale()
        );

        // Handle Guardrails
        if ($this->results['is_off_topic'] ?? false) {
            \Filament\Notifications\Notification::make()
                ->title(app()->getLocale() === 'nl' ? 'Niet relevant' : 'Off-topic detected')
                ->body(app()->getLocale() === 'nl' 
                    ? 'Gelieve alleen verzoeken met betrekking tot verlichting of elektriciteit in te voeren.' 
                    : 'Please only enter requests related to lighting or electricity.')
                ->danger()
                ->send();
            
            $this->results = null;
        }

        if ($this->results['is_gibberish'] ?? false) {
             \Filament\Notifications\Notification::make()
                ->title(app()->getLocale() === 'nl' ? 'Onzin gedetecteerd' : 'Nonsense detected')
                ->body(app()->getLocale() === 'nl' 
                    ? 'De Lead Architect is niet onder de indruk. Probeer het opnieuw met een echt project.' 
                    : 'The Lead Architect is not impressed. Please try again with a real project.')
                ->warning()
                ->send();
        }

        $this->isSimulating = false;
        
        $this->dispatch('simulation-completed');
    }
}
