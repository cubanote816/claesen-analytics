@php
    /**
     * @var array{
     *     eyebrow: string, name: string,
     *     chips: array<int, array{label: string, color?: string, url?: ?string}>,
     *     stat: ?array{value: int|string, label: string},
     *     meta: array<int, array{label: string, value: ?string, placeholder: string, icon?: ?string, url?: ?string, newTab?: bool}>,
     * } $data
     */
    $data = $getState();

    $chipDot = fn (string $color) => match ($color) {
        'success' => 'bg-lime-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-rose-500',
        'gray' => 'bg-gray-400',
        default => 'bg-primary-500',
    };
@endphp

<div class="fieldops-profile-hero w-full">
    <div class="fieldops-profile-hero__top">
        <div class="fieldops-profile-hero__copy">
            <p class="fieldops-profile-hero__eyebrow">{{ $data['eyebrow'] }}</p>
            <h1 class="fieldops-profile-hero__title">{{ $data['name'] }}</h1>

            @if (! empty($data['chips']))
                <div class="fieldops-profile-hero__chips">
                    @foreach ($data['chips'] as $chip)
                        @php $chipTag = ($chip['url'] ?? null) ? 'a' : 'span'; @endphp
                        <{{ $chipTag }}
                            @if ($chip['url'] ?? null) href="{{ $chip['url'] }}" @endif
                            class="fieldops-profile-hero__chip {{ ($chip['url'] ?? null) ? 'fieldops-profile-hero__chip--link' : '' }}"
                        >
                            <span class="fieldops-profile-hero__chip-dot {{ $chipDot($chip['color'] ?? 'info') }}"></span>
                            {{ $chip['label'] }}
                        </{{ $chipTag }}>
                    @endforeach
                </div>
            @endif
        </div>

        @if (! empty($data['stat']))
            <div class="fieldops-profile-hero__stat">
                <div class="fieldops-profile-hero__stat-value">{{ $data['stat']['value'] }}</div>
                <div class="fieldops-profile-hero__stat-label">{{ $data['stat']['label'] }}</div>
            </div>
        @endif
    </div>

    @if (! empty($data['meta']))
        <div class="fieldops-profile-hero__meta">
            @foreach ($data['meta'] as $item)
                <div class="fieldops-profile-hero__meta-item">
                    <p class="fieldops-profile-hero__meta-label">{{ $item['label'] }}</p>

                    @if (($item['type'] ?? null) === 'map')
                        <div class="mt-2">
                            @if ($item['url'] ?? null)
                                <a
                                    href="{{ $item['url'] }}" @if($item['newTab'] ?? false) target="_blank" rel="noopener" @endif
                                    class="fieldops-profile-hero__map-thumb"
                                >
                                    <span class="fieldops-profile-hero__map-pin"></span>
                                </a>
                            @else
                                <span class="fieldops-profile-hero__meta-placeholder">{{ $item['placeholder'] }}</span>
                            @endif
                        </div>
                    @else
                        <div class="fieldops-profile-hero__meta-value">
                            @if ($item['icon'] ?? null)
                                <x-filament::icon :icon="$item['icon']" class="fieldops-profile-hero__meta-icon" />
                            @endif
                            @if (filled($item['value'] ?? null))
                                @if ($item['url'] ?? null)
                                    <a href="{{ $item['url'] }}" @if($item['newTab'] ?? false) target="_blank" rel="noopener" @endif class="hover:underline">{{ $item['value'] }}</a>
                                @else
                                    <span>{{ $item['value'] }}</span>
                                @endif
                            @else
                                <span class="fieldops-profile-hero__meta-placeholder">{{ $item['placeholder'] }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
