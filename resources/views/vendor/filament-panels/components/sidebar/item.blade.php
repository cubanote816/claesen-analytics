{{--
    Override de la vista del sidebar de Filament (WU-S1b del rediseño del backoffice).

    Se mantiene por **tres** desviaciones deliberadas del vendor
    (`vendor/filament/filament/resources/views/components/sidebar/item.blade.php`). Si al
    actualizar Filament el menú vuelve a mostrar dos niveles, ítems sin icono o puntos en vez de
    iconos, es que una de estas tres se perdió — re-aplicarlas sobre la vista nueva:

    1. La recursión pasa `:child-items`, sin lo cual Filament **no baja un segundo nivel** y el
       menú de tres niveles («Website › grupo › página») pierde las páginas: quedarían
       inalcanzables desde el menú. Hay un test que lo vigila.
    2. Un ítem con icono **siempre** lo dibuja. El vendor lo condiciona a `! $subGrouped` o a que
       el sidebar sea colapsable en desktop, y en este panel no lo es: los ítems anidados
       quedaban sin icono y sin estar en el DOM.
    3. El punto sustituto (`fi-sidebar-item-grouped-border`) se dibuja sólo cuando **no** hay
       icono, para no tener las dos cosas a la vez.

    Contexto completo: `odd/tasks/publish-to-live-website.md`, slice S1.
--}}

@props([
    'active' => false,
    'activeChildItems' => false,
    'activeIcon' => null,
    'badge' => null,
    'badgeColor' => null,
    'badgeTooltip' => null,
    'childItems' => [],
    'first' => false,
    'grouped' => false,
    'icon' => null,
    'last' => false,
    'shouldOpenUrlInNewTab' => false,
    'sidebarCollapsible' => true,
    'subGrouped' => false,
    'subNavigation' => false,
    'url',
])

@php
    $sidebarCollapsible = $sidebarCollapsible && filament()->isSidebarCollapsibleOnDesktop();
@endphp

<li
    {{
        $attributes->class([
            'fi-sidebar-item',
            'fi-active' => $active,
            'fi-sidebar-item-has-active-child-items' => $activeChildItems,
            'fi-sidebar-item-has-url' => filled($url),
        ])
    }}
>
    <a
        {{ \Filament\Support\generate_href_html($url, $shouldOpenUrlInNewTab) }}
        @if ($active)
            aria-current="page"
        @endif
        x-on:click="window.matchMedia(`(max-width: 1024px)`).matches && $store.sidebar.close()"
        @if ($sidebarCollapsible && (! $subNavigation))
            x-bind:aria-label="$store.sidebar.isOpen ? null : @js(trim(strip_tags($slot->toHtml())))"
            x-data="{ tooltip: false }"
            x-effect="
                tooltip = $store.sidebar.isOpen
                    ? false
                    : {
                          content: @js($slot->toHtml()),
                          placement: document.dir === 'rtl' ? 'left' : 'right',
                          theme: $store.theme,
                      }
            "
            x-tooltip.html="tooltip"
        @endif
        class="fi-sidebar-item-btn"
    >
        {{--
            WU-S1b: si el ítem TIENE icono, se dibuja. La vista del vendor lo condiciona a
            `! $subGrouped` o a que el sidebar sea colapsable en desktop, y en este panel no lo es:
            los ítems anidados quedaban sin icono **y sin estar en el DOM**. El usuario pidió
            conservar la iconografía que las pantallas ya tenían, así que acá el único requisito es
            que haya icono.
        --}}
        @if (filled($icon))
            {{
                \Filament\Support\generate_icon_html(($active && $activeIcon) ? $activeIcon : $icon, attributes: (new \Filament\Support\View\ComponentAttributeBag([
                    // WU-S1b: el icono se ve **siempre**, también con el sidebar abierto.
                    //
                    // La vista del vendor hace lo contrario: con un ítem sub-agrupado deja el
                    // icono oculto mientras el sidebar está abierto y dibuja un **punto** en su
                    // lugar, y sólo muestra el icono con el sidebar colapsado. El usuario pidió
                    // conservar la iconografía que las pantallas ya tenían, así que acá manda el
                    // icono y el punto no se dibuja (ver la condición de abajo).
                    'x-show' => false,
                ]))->class(['fi-sidebar-item-icon']), size: \Filament\Support\Enums\IconSize::Large)
            }}
        @endif

        @if ((blank($icon) && $grouped) || ($subGrouped && blank($icon)))
            <div
                @if (filled($icon) && $subGrouped && $sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                @endif
                class="fi-sidebar-item-grouped-border"
            >
                @if (! $first)
                    <div
                        class="fi-sidebar-item-grouped-border-part-not-first"
                    ></div>
                @endif

                @if (! $last)
                    <div
                        class="fi-sidebar-item-grouped-border-part-not-last"
                    ></div>
                @endif

                <div class="fi-sidebar-item-grouped-border-part"></div>
            </div>
        @endif

        <span
            @if ($sidebarCollapsible && (! $subNavigation))
                x-show="$store.sidebar.isOpen"
                x-transition:enter="fi-transition-enter"
                x-transition:enter-start="fi-transition-enter-start"
                x-transition:enter-end="fi-transition-enter-end"
            @endif
            class="fi-sidebar-item-label"
        >
            {{ $slot }}
        </span>

        @if (filled($badge))
            <span
                @if ($sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="fi-transition-enter"
                    x-transition:enter-start="fi-transition-enter-start"
                    x-transition:enter-end="fi-transition-enter-end"
                @endif
                class="fi-sidebar-item-badge-ctn"
            >
                <x-filament::badge
                    :color="$badgeColor"
                    :tooltip="$badgeTooltip"
                >
                    {{ $badge }}
                </x-filament::badge>
            </span>
        @endif
    </a>

    @if ($childItems && (blank($url) || $active || $activeChildItems))
        <ul class="fi-sidebar-sub-group-items">
            @foreach ($childItems as $childItem)
                @php
                    $isChildItemChildItemsActive = $childItem->isChildItemsActive();
                    $isChildActive = (! $isChildItemChildItemsActive) && $childItem->isActive();
                    $childItemActiveIcon = $childItem->getActiveIcon();
                    $childItemBadge = $childItem->getBadge();
                    $childItemBadgeColor = $childItem->getBadgeColor($childItemBadge);
                    $childItemBadgeTooltip = $childItem->getBadgeTooltip($childItemBadge);
                    $childItemIcon = $childItem->getIcon();
                    $shouldChildItemOpenUrlInNewTab = $childItem->shouldOpenUrlInNewTab();
                    $childItemUrl = $childItem->getUrl();
                    $childItemExtraAttributes = $childItem->getExtraAttributeBag();
                @endphp

                <x-filament-panels::sidebar.item
                    :active="$isChildActive"
                    :active-child-items="$isChildItemChildItemsActive"
                    :active-icon="$childItemActiveIcon"
                    :badge="$childItemBadge"
                    :badge-color="$childItemBadgeColor"
                    :badge-tooltip="$childItemBadgeTooltip"
                    {{--
                        WU-S1b: UNICA línea cambiada respecto de la vista del vendor.

                        Sin `:child-items`, la recursión **no baja un segundo nivel**: Filament
                        renderiza hijos de hijos nunca, así que un menú de tres niveles
                        («Website › grupo › página», que es lo que pide el prototipo aprobado) no se
                        podía dibujar. La alternativa era aplanar el menú y perder los cuatro
                        grupos. La sobreescritura se mantiene por eso, y si al actualizar Filament
                        el menú vuelve a mostrar sólo dos niveles, es esto lo que se perdió.
                    --}}
                    :child-items="$childItem->getChildItems()"
                    :first="$loop->first"
                    grouped
                    :icon="$childItemIcon"
                    :last="$loop->last"
                    :should-open-url-in-new-tab="$shouldChildItemOpenUrlInNewTab"
                    sub-grouped
                    :sub-navigation="$subNavigation"
                    :url="$childItemUrl"
                    :attributes="\Filament\Support\prepare_inherited_attributes($childItemExtraAttributes)"
                >
                    {{ $childItem->getLabel() }}
                </x-filament-panels::sidebar.item>
            @endforeach
        </ul>
    @endif
</li>
