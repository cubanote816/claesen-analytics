@php
    /**
     * @var array{
     *     eyebrow: string, name: string,
     *     chips: array<int, array{label: string, color?: string, url?: ?string}>,
     *     stat: ?array{value: int|string, label: string},
     *     facts?: array<int, array{label: string, value: ?string, placeholder: string, icon?: ?string, url?: ?string, newTab?: bool, type?: string}>,
     *     meta: array<int, array{label: string, value: ?string, placeholder: string, icon?: ?string, url?: ?string, newTab?: bool}>,
     * } $data
     */
    $data = $getState();
    $details = array_values(array_filter(
        array_merge($data['facts'] ?? [], $data['meta'] ?? []),
        fn ($item) => $item !== null,
    ));

    $chipDot = fn (string $color) => match ($color) {
        'success' => 'bg-lime-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-rose-500',
        'gray' => 'bg-gray-400',
        default => 'bg-primary-500',
    };
@endphp

@once
    <style>
        .fieldops-profile-hero {
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.22);
            border-radius: 1.25rem;
            background:
                radial-gradient(circle at top left, rgba(0, 174, 239, 0.08), transparent 34%),
                linear-gradient(180deg, #ffffff 0%, #f8fbfe 100%);
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }

        .dark .fieldops-profile-hero {
            border-color: rgba(255, 255, 255, 0.08);
            background:
                radial-gradient(circle at top left, rgba(0, 174, 239, 0.16), transparent 30%),
                linear-gradient(180deg, #171725 0%, #11111a 100%);
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08), 0 24px 60px -18px rgba(0, 0, 0, 0.62);
        }

        .fieldops-profile-hero__top {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 1rem 1.5rem;
            align-items: end;
            padding: 1.5rem 1.5rem 1.15rem;
        }

        .fieldops-profile-hero__copy {
            min-width: 0;
        }

        .fieldops-profile-hero__eyebrow {
            color: #008fc8;
            font-size: 0.6875rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .fieldops-profile-hero__title {
            margin-top: 0.4rem;
            color: #0f172a;
            font-size: clamp(1.9rem, 3vw, 2.8rem);
            font-weight: 900;
            letter-spacing: -0.035em;
            line-height: 1.02;
        }

        .dark .fieldops-profile-hero__title {
            color: #f8fafc;
        }

        .fieldops-profile-hero__chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.9rem;
        }

        .fieldops-profile-hero__chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            min-height: 2.1rem;
            padding: 0.35rem 0.8rem;
            border: 1px solid #d5dde6;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.78);
            color: #334155;
            font-size: 0.9rem;
            line-height: 1;
            white-space: nowrap;
        }

        .dark .fieldops-profile-hero__chip {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.04);
            color: #dbeafe;
        }

        .fieldops-profile-hero__chip--link:hover {
            text-decoration: underline;
        }

        .fieldops-profile-hero__chip-dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 999px;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.45);
        }

        .dark .fieldops-profile-hero__chip-dot {
            box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.45);
        }

        .fieldops-profile-hero__stat {
            min-width: 7rem;
            text-align: right;
        }

        .fieldops-profile-hero__stat-value {
            color: #009bd6;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: clamp(2.2rem, 4vw, 3.4rem);
            font-weight: 900;
            line-height: 0.95;
        }

        .fieldops-profile-hero__stat-label {
            margin-top: 0.45rem;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .dark .fieldops-profile-hero__stat-label {
            color: #94a3b8;
        }

        .fieldops-profile-hero__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            padding: 1rem 1.5rem 1.35rem;
            border-top: 1px solid rgba(148, 163, 184, 0.16);
        }

        .dark .fieldops-profile-hero__meta {
            border-top-color: rgba(255, 255, 255, 0.08);
        }

        .fieldops-profile-hero__meta-item {
            flex: 1 1 12rem;
            min-width: 11rem;
            padding: 0.95rem 1rem;
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.5);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }

        .dark .fieldops-profile-hero__meta-item {
            border-color: rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.025);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.2);
        }

        .fieldops-profile-hero__meta-label {
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .dark .fieldops-profile-hero__meta-label {
            color: #94a3b8;
        }

        .fieldops-profile-hero__meta-value {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            margin-top: 0.45rem;
            color: #0f172a;
            font-size: 0.98rem;
            font-weight: 600;
            line-height: 1.35;
        }

        .dark .fieldops-profile-hero__meta-value {
            color: #e2e8f0;
        }

        .fieldops-profile-hero__meta-icon {
            flex-shrink: 0;
            width: 0.9rem;
            height: 0.9rem;
            color: #94a3b8;
        }

        .fieldops-profile-hero__meta-placeholder {
            color: #94a3b8;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .fieldops-profile-hero__map-thumb {
            position: relative;
            display: block;
            width: 6rem;
            height: 3.6rem;
            overflow: hidden;
            border: 1px solid #d5dde6;
            border-radius: 0.8rem;
            background:
                linear-gradient(135deg, rgba(0, 174, 239, 0.1), rgba(229, 241, 248, 0.55)),
                radial-gradient(circle at center, rgba(233, 236, 239, 0.65), transparent 70%);
        }

        .dark .fieldops-profile-hero__map-thumb {
            border-color: rgba(255, 255, 255, 0.1);
            background:
                linear-gradient(135deg, rgba(0, 174, 239, 0.2), rgba(255, 255, 255, 0.03)),
                radial-gradient(circle at center, rgba(255, 255, 255, 0.08), transparent 70%);
        }

        .fieldops-profile-hero__map-thumb::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(to right, rgba(148, 163, 184, 0.16) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(148, 163, 184, 0.16) 1px, transparent 1px);
            background-size: 0.65rem 0.65rem;
            opacity: 0.7;
        }

        .fieldops-profile-hero__map-thumb::after {
            content: "";
            position: absolute;
            left: 50%;
            top: 50%;
            width: 0.7rem;
            height: 0.7rem;
            transform: translate(-50%, -70%);
            border-radius: 999px;
            background: #e6007e;
            box-shadow: 0 0 0 0.25rem rgba(230, 0, 126, 0.16);
        }

        .fieldops-profile-hero__map-pin {
            position: absolute;
            left: 50%;
            top: 50%;
            width: 0.38rem;
            height: 0.38rem;
            transform: translate(-50%, -20%);
            border-radius: 999px;
            background: #ffffff;
        }

        @media (max-width: 768px) {
            .fieldops-profile-hero__top {
                grid-template-columns: minmax(0, 1fr);
            }

            .fieldops-profile-hero__stat {
                text-align: left;
            }

            .fieldops-profile-hero__actions {
                align-items: flex-start;
            }
        }
    </style>
@endonce

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
                            @if ($chip['url'] ?? null) {{ \Filament\Support\generate_href_html($chip['url']) }} @endif
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

    @if (! empty($details))
        <div class="fieldops-profile-hero__meta">
            @foreach ($details as $item)
                <div class="fieldops-profile-hero__meta-item">
                    <p class="fieldops-profile-hero__meta-label">{{ $item['label'] }}</p>

                    @if (($item['type'] ?? null) === 'map')
                        <div class="mt-2">
                            @if ($item['url'] ?? null)
                                <a
                                    {{ \Filament\Support\generate_href_html($item['url'], (bool) ($item['newTab'] ?? false)) }} @if($item['newTab'] ?? false) rel="noopener" @endif
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
                                    <a {{ \Filament\Support\generate_href_html($item['url'], (bool) ($item['newTab'] ?? false)) }} @if($item['newTab'] ?? false) rel="noopener" @endif class="hover:underline">{{ $item['value'] }}</a>
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
