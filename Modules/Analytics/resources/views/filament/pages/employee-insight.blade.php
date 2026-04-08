<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Input Section -->
        <x-filament::section>
            <x-slot name="heading">
                {{ app()->getLocale() === 'nl' ? 'Kies Technicus' : 'Select Technician' }}
            </x-slot>

            <form wire:submit="analyze">
                {{ $this->form }}

                <div class="mt-4">
                    <x-filament::button type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="analyze">
                            {{ app()->getLocale() === 'nl' ? 'Genereer Arquetype' : 'Generate Archetype' }}
                        </span>
                        <span wire:loading wire:target="analyze">
                            {{ app()->getLocale() === 'nl' ? 'Analyseren...' : 'Analyzing...' }}
                        </span>
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <!-- Result Section -->
        <x-filament::section>
            <x-slot name="heading">
                {{ app()->getLocale() === 'nl' ? 'HR Arquetype & Trend (IA)' : 'HR Archetype & Trend (AI)' }}
            </x-slot>

            <div class="prose dark:prose-invert max-w-none">
                @if($analysisResult)
                    <div class="flex items-center gap-4 p-4 mb-4 rounded-lg bg-gray-50 dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="text-4xl shadow bg-white dark:bg-gray-900 rounded-full h-16 w-16 flex items-center justify-center">
                            {{ $analysisResult['archetype_icon'] ?? '👤' }}
                        </div>
                        <div class="flex-1">
                            <h3 class="mt-0 mb-1 font-bold text-xl text-gray-900 dark:text-gray-100">
                                {{ $analysisResult['archetype_label'] ?? 'Unknown' }}
                            </h3>
                            <div class="flex gap-2 text-sm">
                                <span class="bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300 py-1 px-2 rounded-full font-medium">Trend: {{ $analysisResult['efficiency_trend'] ?? 'N/A' }}</span>
                                <span class="bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-300 py-1 px-2 rounded-full font-medium">Burnout Risk: {{ $analysisResult['burnout_risk_score'] ?? 0 }}%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 p-4 border-l-4 border-amber-500 bg-amber-50 dark:bg-amber-900/20 text-gray-800 dark:text-gray-300 rounded-r-lg">
                        <p class="m-0 italic"><strong class="not-italic block mb-1">Manager Insight:</strong> {{ $analysisResult['manager_insight'] ?? '' }}</p>
                    </div>
                @else
                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400 italic">
                        {{ app()->getLocale() === 'nl' ? 'Selecteer een technicus en klik om hun 6-maanden HR profiel en burnout-risico via IA te evalueren.' : 'Select a technician to evaluate their 6-month HR profile and burnout risk via AI.' }}
                    </div>
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
