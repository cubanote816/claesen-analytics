<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2 text-danger-600 dark:text-danger-400">
                <x-heroicon-o-exclamation-triangle class="h-6 w-6" />
                <span class="font-bold">Cash Flow Watchdog (Draken)</span>
            </div>
        </x-slot>

        <x-slot name="description">
            Monday Morning Risk Report - AI Augmented
        </x-slot>

        <div class="prose dark:prose-invert max-w-none p-4 rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 whitespace-pre-wrap font-mono text-sm leading-relaxed">
            @if($report)
                {!! nl2br(e($report)) !!}
            @else
                <div class="flex items-center gap-2 text-gray-500">
                    <x-filament::loading-indicator class="h-5 w-5" />
                    <span>Laden van de rapportage...</span>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
