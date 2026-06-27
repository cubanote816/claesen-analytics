<?php

namespace Modules\Intelligence\Filament\Pages;

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
use Modules\Intelligence\Services\BudgetAssistantService;

class OfferSimulator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $slug = 'offer-simulator';
    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return 'DEMO';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    protected string $view = 'intelligence::filament.pages.offer-simulator';

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
        return __('navigation.groups.intelligence_hub');
    }

    public static function getNavigationLabel(): string
    {
        return __('intelligence::offer_simulator.navigation_label');
    }

    public function getTitle(): string
    {
        return __('intelligence::offer_simulator.title');
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make(__('intelligence::offer_simulator.sections.project_details'))
                    ->schema([
                        Textarea::make('description')
                            ->label(__('intelligence::offer_simulator.fields.description'))
                            ->placeholder(__('intelligence::offer_simulator.fields.description_placeholder'))
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
                            ->label(__('intelligence::offer_simulator.fields.category'))
                            ->options([
                                'Sportverlichting' => 'Sportverlichting',
                                'Industrie' => 'Industrie',
                                'Openbare Verlichting' => 'Openbare Verlichting',
                                'Masten' => 'Masten',
                            ])
                            ->required(),

                        TextInput::make('zipcode')
                            ->label(__('intelligence::offer_simulator.fields.zipcode'))
                            ->numeric()
                            ->required()
                            ->maxLength(5),

                        Slider::make('complexity')
                            ->label(__('intelligence::offer_simulator.fields.complexity'))
                            ->helperText(__('intelligence::offer_simulator.fields.complexity_hint'))
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
                ->label(__('intelligence::offer_simulator.actions.simulate'))
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
                ->title(__('intelligence::offer_simulator.notifications.off_topic_title'))
                ->body(__('intelligence::offer_simulator.notifications.off_topic_body'))
                ->danger()
                ->send();

            $this->results = null;
        }

        if ($this->results['is_gibberish'] ?? false) {
            \Filament\Notifications\Notification::make()
                ->title(__('intelligence::offer_simulator.notifications.gibberish_title'))
                ->body(__('intelligence::offer_simulator.notifications.gibberish_body'))
                ->warning()
                ->send();
        }

        $this->isSimulating = false;
        
        $this->dispatch('simulation-completed');
    }
}
