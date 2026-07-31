@php
    /** @var array<int, array{label: string, items: array<int, array{label: string, url: string}>}> $groups */
    $groups = $getState();
@endphp

<div class="flex flex-col gap-4">
    @foreach ($groups as $group)
        <div class="flex flex-wrap items-center gap-2">
            <span class="w-28 shrink-0 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $group['label'] }}</span>
            @forelse ($group['items'] as $item)
                <a
                    {{ \Filament\Support\generate_href_html($item['url']) }}
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-sm text-gray-700 hover:border-primary-400 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
                >{{ $item['label'] }}</a>
            @empty
                <span class="text-sm text-gray-400 dark:text-gray-500">&mdash;</span>
            @endforelse
        </div>
    @endforeach
</div>
