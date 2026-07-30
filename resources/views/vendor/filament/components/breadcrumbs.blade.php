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
    segments, which either wrap into a broken multi-line layout (Filament's default) or need
    horizontal scrolling to read (an earlier attempt here, rejected — see git history on this
    file). What the user actually wants: keep it a single normal horizontal breadcrumb line,
    just collapse the *distant* ancestor instances (e.g. the specific Complex/Terrain names —
    the parts furthest from where you are) into one "First > … > <rest kept in full>" line.
    Only the middle gets collapsed; the tail (closest to the current page) always stays fully
    expanded and clickable, same styling as a normal breadcrumb.

    Only triggers past 7 entries — short breadcrumbs everywhere else in the panel (Safety,
    Mailing, etc. never build a chain this deep) render completely unchanged.
--}}

@php
    $entries = collect($breadcrumbs)
        ->map(fn ($label, $url) => ['url' => $url, 'label' => $label])
        ->values();

    $tailSize = 7;

    if ($entries->count() > $tailSize) {
        $entries = collect([
            $entries->first(),
            ['url' => null, 'label' => '…'],
            ...$entries->slice(-$tailSize)->all(),
        ]);
    }
@endphp

<nav {{ $attributes->class(['fi-breadcrumbs']) }}>
    <ol class="fi-breadcrumbs-list">
        @foreach ($entries as $entry)
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

                @if ($entry['url'] === null || is_int($entry['url']))
                    <span class="fi-breadcrumbs-item-label">
                        {{ $entry['label'] }}
                    </span>
                @else
                    <a
                        {{ generate_href_html($entry['url']) }}
                        class="fi-breadcrumbs-item-label"
                    >
                        {{ $entry['label'] }}
                    </a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
