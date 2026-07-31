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

    Two things this override adds on top of Filament's default:

    1. "Type" segments (a key prefixed with 'fieldops-breadcrumb-unlinked:' — see
       Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs) render as plain text,
       same as Filament's own is_int($url) convention for its "current page" entry.
       Both non-link cases now also get a dimmed style (see theme.css) so it's
       visible at rest, not just discoverable by trying to click — links and
       plain labels used to render identically (same color, only a hover
       transition betrayed which was which).

    2. The line no longer collapses by a fixed entry-count guess. Every entry
       always renders in the DOM; an Alpine component measures whether the row
       has actually wrapped onto a second line (real layout, not a heuristic)
       and, only if so, hides entries in two passes: first the non-link "type"
       segments (they carry no navigation value, so they're the cheapest to
       give up), then — if still wrapping — real entries one at a time
       starting right after the first (Complexes always stays, the last entry
       always stays). Each contiguous hidden run collapses into a single "…",
       not one "…" per hidden entry. Re-measured on resize (ResizeObserver on
       the breadcrumb's own container, so a sidebar toggle counts too, not
       just a window resize). Short breadcrumbs elsewhere in the panel
       (Safety, Mailing…) never wrap, so this never does anything there.
--}}

@php
    $unlinkedPrefix = 'fieldops-breadcrumb-unlinked:';

    $entries = collect($breadcrumbs)
        ->map(fn ($label, $url) => ['url' => $url, 'label' => $label])
        ->values()
        ->map(function (array $entry) use ($unlinkedPrefix) {
            $entry['isTypeLabel'] = is_string($entry['url']) && str_starts_with($entry['url'], $unlinkedPrefix);
            $entry['isLink'] = $entry['url'] !== null && ! is_int($entry['url']) && ! $entry['isTypeLabel'];

            return $entry;
        });

    $lastIndex = max($entries->count() - 1, 0);

    $typeLabelIndexes = $entries
        ->filter(fn (array $entry, int $index) => $entry['isTypeLabel'] && $index !== $lastIndex)
        ->keys()
        ->values()
        ->all();
@endphp

<nav
    x-data="fieldopsBreadcrumbs({{ Illuminate\Support\Js::from(['typeLabelIndexes' => $typeLabelIndexes, 'totalEntries' => $entries->count()]) }})"
    {{ $attributes->class(['fi-breadcrumbs']) }}
>
    <ol x-ref="list" class="fi-breadcrumbs-list">
        @foreach ($entries as $index => $entry)
            <li x-show="isEllipsisStart({{ $index }})" class="fi-breadcrumbs-item" aria-hidden="true">
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

                <span class="fi-breadcrumbs-item-label">&hellip;</span>
            </li>

            <li x-show="! isHidden({{ $index }})" class="fi-breadcrumbs-item">
                @if ($index > 0)
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

                @if ($entry['isLink'])
                    <a
                        {{ generate_href_html($entry['url']) }}
                        class="fi-breadcrumbs-item-label"
                    >
                        {{ $entry['label'] }}
                    </a>
                @else
                    <span class="fi-breadcrumbs-item-label">
                        {{ $entry['label'] }}
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>

@script
<script>
    Alpine.data('fieldopsBreadcrumbs', ({ typeLabelIndexes, totalEntries }) => ({
        collapsedIndexes: [],

        init() {
            this.recalc();

            // Width-only guard: collapsing entries shrinks the row from two
            // lines back to one, which changes this same container's HEIGHT —
            // observing the container naively re-fires the observer on that
            // self-inflicted change, racing a new recalc() against the one
            // still finishing. Only react when the width actually changed.
            let lastWidth = null;

            new ResizeObserver((entries) => {
                const width = entries[0]?.contentRect?.width;

                if (width === undefined || width === lastWidth) {
                    return;
                }

                lastWidth = width;
                this.recalc();
            }).observe(this.$el.parentElement ?? this.$el);
        },

        // $nextTick alone only guarantees Alpine finished patching the DOM,
        // not that the browser has actually laid out/painted the result —
        // measuring offsetTop right after it was a real, observed race
        // (readings reflected the PREVIOUS layout). Double rAF is the
        // standard way to wait past both.
        frame() {
            return new Promise((resolve) => {
                this.$nextTick(() => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            });
        },

        isHidden(index) {
            return this.collapsedIndexes.includes(index);
        },

        isEllipsisStart(index) {
            if (index === 0 || ! this.isHidden(index)) {
                return false;
            }

            return ! this.isHidden(index - 1);
        },

        isWrapping() {
            const items = Array.from(this.$refs.list.children).filter(
                (el) => el.offsetWidth > 0 || el.offsetHeight > 0,
            );

            if (items.length < 2) {
                return false;
            }

            const firstTop = items[0].offsetTop;

            return items.some((el) => el.offsetTop > firstTop + 2);
        },

        recalculating: false,
        recalcQueued: false,

        // Guards against two recalc() runs interleaving their awaits (e.g. a
        // genuine width change landing while a previous pass is still
        // stepping through frames) — without this, both runs write to the
        // same collapsedIndexes and the final state depends on whichever
        // await happened to resolve last.
        async recalc() {
            if (this.recalculating) {
                this.recalcQueued = true;

                return;
            }

            this.recalculating = true;
            await this.runCollapsePasses();
            this.recalculating = false;

            if (this.recalcQueued) {
                this.recalcQueued = false;
                await this.recalc();
            }
        },

        async runCollapsePasses() {
            if (totalEntries < 2) {
                return;
            }

            const lastIndex = totalEntries - 1;

            this.collapsedIndexes = [];
            await this.frame();

            if (! this.isWrapping()) {
                return;
            }

            const collapsed = new Set(typeLabelIndexes);
            this.collapsedIndexes = Array.from(collapsed);
            await this.frame();

            if (! this.isWrapping()) {
                return;
            }

            for (let index = 1; index < lastIndex; index++) {
                if (collapsed.has(index)) {
                    continue;
                }

                collapsed.add(index);
                this.collapsedIndexes = Array.from(collapsed);
                await this.frame();

                if (! this.isWrapping()) {
                    return;
                }
            }
        },
    }));
</script>
@endscript
