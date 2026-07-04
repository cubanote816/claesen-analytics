@php
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
    "
>
    <div class="flex justify-end mb-2">
        <label for="safety-stats-period" class="sr-only">
            {{ __('safety::inspections.widgets.stats.period_label') }}
        </label>
        <select
            id="safety-stats-period"
            wire:model.live="period"
            class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm px-2 py-1"
        >
            @foreach ($this->getPeriodOptions() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    {{ $this->content }}
</x-filament-widgets::widget>
