@php
    use Illuminate\View\ComponentAttributeBag;

    use function Filament\Support\generate_icon_html;
    use function Filament\Support\generate_href_html;
@endphp

@props([
    'breadcrumbs' => [],
])

{{--
    Local override of vendor/filament/support/resources/views/components/breadcrumbs.blade.php
    (Laravel's standard vendor-view-override convention — resources/views/vendor/{namespace}/...
    takes priority over the package's own views automatically, no service provider change needed).

    Deep hierarchy pages (FieldOps: Complexes > ... > Luminaires > View) can produce 10+
    segments. Filament's default single flex-wrap line either wraps into a broken multi-line
    layout, or (CLA-278 first attempt) scrolls horizontally — user explicitly rejected the
    scroll version and asked for this instead: collapse the distant/less relevant ancestors
    into one compact "First > ... > LastCollapsed" line, and show the last 6 entries (the
    part closest to the current page) as a vertical stack instead.

    Only triggers past 6 entries — short breadcrumbs everywhere else in the panel (Safety,
    Mailing, etc. never build a chain this deep) fall through to the original single-line
    markup below, unchanged.

    The FieldOps breadcrumb array (Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs)
    always alternates [type, record, type, record, ..., action] — taking the last 6 entries
    of *any* longer chain always lands on the same [record, type, record, type, record,
    action] pattern (the action is always last, so parity counting from the end is fixed
    regardless of total length). That's what the even/odd index styling below relies on.
--}}

@php
    $entries = collect($breadcrumbs)
        ->map(fn ($label, $url) => ['url' => $url, 'label' => $label])
        ->values();
    $shouldCollapse = $entries->count() > 6;
@endphp

<nav {{ $attributes->class(['fi-breadcrumbs']) }}>
    @if ($shouldCollapse)
        @php
            $collapsedHead = $entries->first();
            $collapsedTail = $entries->slice(0, -6)->last();
            $stack = $entries->slice(-6)->values();
        @endphp

        <ol class="claesen-breadcrumbs-collapsed">
            <li class="claesen-breadcrumbs-collapsed-item">
                <a {{ generate_href_html($collapsedHead['url']) }} class="claesen-breadcrumbs-collapsed-link">
                    {{ $collapsedHead['label'] }}
                </a>
            </li>
            <li class="claesen-breadcrumbs-collapsed-item" aria-hidden="true">
                {{ generate_icon_html(\Filament\Support\Icons\Heroicon::ChevronRight, attributes: (new ComponentAttributeBag)->class(['claesen-breadcrumbs-collapsed-separator'])) }}
                <span class="claesen-breadcrumbs-collapsed-ellipsis">&hellip;</span>
                {{ generate_icon_html(\Filament\Support\Icons\Heroicon::ChevronRight, attributes: (new ComponentAttributeBag)->class(['claesen-breadcrumbs-collapsed-separator'])) }}
            </li>
            <li class="claesen-breadcrumbs-collapsed-item">
                <a {{ generate_href_html($collapsedTail['url']) }} class="claesen-breadcrumbs-collapsed-link">
                    {{ $collapsedTail['label'] }}
                </a>
            </li>
        </ol>

        <ol class="claesen-breadcrumbs-stack">
            @foreach ($stack as $i => $entry)
                @php
                    $isCurrent = is_int($entry['url']);
                    $isRecord = ! $isCurrent && ($i % 2 === 0);
                @endphp
                <li
                    @class([
                        'claesen-breadcrumbs-stack-item',
                        'claesen-breadcrumbs-stack-item--record' => $isRecord,
                        'claesen-breadcrumbs-stack-item--type' => ! $isRecord && ! $isCurrent,
                        'claesen-breadcrumbs-stack-item--current' => $isCurrent,
                    ])
                >
                    @if ($isCurrent)
                        <span class="claesen-breadcrumbs-stack-label">{{ $entry['label'] }}</span>
                    @else
                        <a {{ generate_href_html($entry['url']) }} class="claesen-breadcrumbs-stack-label">
                            {{ $entry['label'] }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ol>
    @else
        <ol class="fi-breadcrumbs-list">
            @foreach ($breadcrumbs as $url => $label)
                <li class="fi-breadcrumbs-item">
                    @if (! $loop->first)
                        {{
                            generate_icon_html(\Filament\Support\Icons\Heroicon::ChevronRight, alias: \Filament\Support\View\SupportIconAlias::BREADCRUMBS_SEPARATOR, attributes: (new ComponentAttributeBag)->class([
                                'fi-breadcrumbs-item-separator fi-ltr',
                            ]))
                        }}

                        {{
                            generate_icon_html(\Filament\Support\Icons\Heroicon::ChevronLeft, alias: \Filament\Support\View\SupportIconAlias::BREADCRUMBS_SEPARATOR_RTL, attributes: (new ComponentAttributeBag)->class([
                                'fi-breadcrumbs-item-separator fi-rtl',
                            ]))
                        }}
                    @endif

                    @if (is_int($url))
                        <span class="fi-breadcrumbs-item-label">
                            {{ $label }}
                        </span>
                    @else
                        <a
                            {{ generate_href_html($url) }}
                            class="fi-breadcrumbs-item-label"
                        >
                            {{ $label }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</nav>
