<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Input Section -->
        <x-filament::section>
            <x-slot name="heading">
                {{ app()->getLocale() === 'nl' ? 'Configureer Bod' : 'Configure Offer' }}
            </x-slot>

            <form wire:submit="submit">
                {{ $this->form }}

                <div class="mt-4">
                    <x-filament::button type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submit">
                            {{ app()->getLocale() === 'nl' ? 'Simuleer Bod' : 'Simulate Offer' }}
                        </span>
                        <span wire:loading wire:target="submit">
                            {{ app()->getLocale() === 'nl' ? 'Bezig met analyseren...' : 'Analyzing...' }}
                        </span>
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <!-- RAG / AI Result Section -->
        <x-filament::section>
            <x-slot name="heading">
                {{ app()->getLocale() === 'nl' ? 'IA Analyse & Feedback' : 'AI Analysis & Feedback' }}
            </x-slot>

            <div class="prose dark:prose-invert max-w-none"
                 @if($hashKey) wire:poll.2s="checkProgress" @endif
            >
                @if($hashKey)
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/30 rounded-lg whitespace-pre-wrap font-mono text-sm border-l-4 border-blue-500 animate-pulse text-blue-700 dark:text-blue-300">
                        <div class="flex items-center gap-3">
                            <x-filament::loading-indicator class="h-5 w-5" />
                            <span>{{ app()->getLocale() === 'nl' ? 'Consultando historial y construyendo contexto (RAG)...' : 'Querying history and building RAG context...' }}</span>
                        </div>
                    </div>
                @elseif($analysisResult)
                    <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-lg whitespace-pre-wrap font-mono text-sm border-l-4 border-indigo-500">
                        {{ $analysisResult }}
                    </div>
                @else
                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400 italic">
                        {{ app()->getLocale() === 'nl' ? 'Vul de gegevens in en start de simulatie om resultaten uit de RAG engine te zien.' : 'Fill in the details and start the simulation to see RAG engine results.' }}
                    </div>
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
