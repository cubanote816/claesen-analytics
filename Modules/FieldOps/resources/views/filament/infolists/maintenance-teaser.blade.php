@php
    /** @var array<int, array{date: ?string, type: ?string, status: string}> $records */
    $records = $getState();
@endphp

@if (count($records) > 0)
    <div class="flex flex-col gap-2">
        @foreach ($records as $record)
            <div class="flex items-center gap-2 text-sm">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $record['status'] === 'open' ? 'bg-amber-500' : 'bg-lime-500' }}"></span>
                <span class="text-gray-700 dark:text-gray-300">{{ $record['type'] }}</span>
                <span class="text-gray-400 dark:text-gray-500">&mdash; {{ $record['date'] }}</span>
            </div>
        @endforeach
    </div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('fieldops::resource.luminaires.no_maintenance') }}</p>
@endif
