@props([
    'actions' => [],
    'actionsAlignment' => null,
    'breadcrumbs' => [],
    'heading' => null,
    'subheading' => null,
])

{{--
    Local override of vendor/filament/filament/resources/views/components/header/index.blade.php
    (same vendor-view-override convention as components/breadcrumbs.blade.php next to this file).

    Filament's default puts the breadcrumb inside the same flex row as the heading and the
    header action buttons (.fi-header is flex-row + justify-between, breadcrumb lives in the
    left-hand div alongside the heading). On a page with several header actions (buttons like
    "Open in frame" / "Schedule maintenance" / "Replace luminaire" / "Edit"), that squeezes the
    breadcrumb into a fraction of the row's width, forcing it to wrap sooner than it needs to.

    Pulled the breadcrumb out to its own full-width row above everything else instead — the
    heading/subheading + action buttons keep Filament's original flex-row layout below it.
--}}

<div class="claesen-page-header-wrap">
    @if ($breadcrumbs)
        <div class="claesen-page-header-breadcrumbs">
            <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" />
        </div>
    @endif

    <header {{ $attributes->class(['fi-header']) }}>
        <div>
            @if (filled($heading))
                <h1 class="fi-header-heading">
                    {{ $heading }}
                </h1>
            @endif

            @if (filled($subheading))
                <p class="fi-header-subheading">
                    {{ $subheading }}
                </p>
            @endif
        </div>

        @php
            $beforeActions = \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE, scopes: $this->getRenderHookScopes());
            $afterActions = \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::PAGE_HEADER_ACTIONS_AFTER, scopes: $this->getRenderHookScopes());
        @endphp

        @if (filled($beforeActions) || $actions || filled($afterActions))
            <div class="fi-header-actions-ctn">
                {{ $beforeActions }}

                @if ($actions)
                    <x-filament::actions
                        :actions="$actions"
                        :alignment="$actionsAlignment"
                    />
                @endif

                {{ $afterActions }}
            </div>
        @endif
    </header>
</div>
