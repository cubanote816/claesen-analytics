@php
    /**
     * @var array{
     *     eyebrow: string, name: string,
     *     chips: array<int, array{label: string, color?: string}>,
     *     stat: ?array{value: int|string, label: string},
     *     meta: array<int, array{label: string, value: ?string, placeholder: string, icon?: ?string, url?: ?string, newTab?: bool}>,
     * } $data
     */
    $data = $getState();

    $chipDot = fn (string $color) => match ($color) {
        'success' => 'bg-lime-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-rose-500',
        default => 'bg-primary-500',
    };
@endphp

<div class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $data['eyebrow'] }}</p>
            <h1 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white sm:text-3xl">{{ $data['name'] }}</h1>
            @if (! empty($data['chips']))
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($data['chips'] as $chip)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 px-2.5 py-1 text-xs text-gray-600 dark:border-white/10 dark:text-gray-300">
                            <span class="h-1.5 w-1.5 rounded-full {{ $chipDot($chip['color'] ?? 'info') }}"></span>
                            {{ $chip['label'] }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
        @if (! empty($data['stat']))
            <div class="text-right">
                <div class="font-mono text-3xl leading-none text-primary-600 dark:text-primary-400">{{ $data['stat']['value'] }}</div>
                <div class="mt-1 text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $data['stat']['label'] }}</div>
            </div>
        @endif
    </div>

    @if (! empty($data['meta']))
        <div class="mt-5 flex flex-wrap gap-x-10 gap-y-3 border-y border-gray-200 py-4 dark:border-white/10">
            @foreach ($data['meta'] as $item)
                <div class="min-w-[10rem]">
                    <p class="text-[0.65rem] font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $item['label'] }}</p>
                    <div class="mt-1 flex items-center gap-1.5 text-sm text-gray-900 dark:text-gray-100">
                        @if ($item['icon'] ?? null)
                            <x-filament::icon :icon="$item['icon']" class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                        @endif
                        @if (filled($item['value'] ?? null))
                            @if ($item['url'] ?? null)
                                <a href="{{ $item['url'] }}" @if($item['newTab'] ?? false) target="_blank" rel="noopener" @endif class="hover:underline">{{ $item['value'] }}</a>
                            @else
                                <span>{{ $item['value'] }}</span>
                            @endif
                        @else
                            <span class="text-gray-400 dark:text-gray-500">{{ $item['placeholder'] }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
