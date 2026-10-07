<?php

namespace App\Filament\Clusters\Website;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Navigation\NavigationItem;
use UnitEnum;

class WebsiteCluster extends Cluster
{
    protected static BackedEnum | string | null $navigationIcon = 'heroicon-o-globe-alt';
    
    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('website.cluster_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.content_website');
    }

    /**
     * El submenú, **dentro del sidebar**, colgado del ítem Website.
     *
     * Filament corta a propósito el registro de las páginas de un clúster
     * (`Page::registerNavigationItems()` sale temprano si hay clúster), así que sus ítems **nunca**
     * llegan al sidebar solos — y por eso el submenú se renderizaba al costado del contenido.
     *
     * Pero `getNavigationItems()` **sí** funciona en una página de clúster: lo que no corre es el
     * registro. Así que los ítems se piden a los componentes y se cuelgan como `childItems` del
     * ítem del clúster. El sidebar **recursiona** sobre `childItems` (renderiza cada hijo con el
     * mismo componente), y `NavigationManager` **preserva** los `childItems` ya puestos al fusionar
     * los que declaran `parentItem`. Con eso, «Website» queda con sus cuatro grupos y cada grupo
     * con sus ítems y sus badges, y todo dentro de «Content & Website» — sin registrar nada a mano
     * en el panel ni duplicar etiquetas, iconos u órdenes que ya declara cada componente.
     */
    public static function getNavigationItems(): array
    {
        $groups = collect(WebsiteNavigationGroups::all());

        $itemsByGroup = collect(static::getClusteredComponents())
            ->flatMap(fn (string $component): array => $component::getNavigationItems())
            ->groupBy(fn (NavigationItem $item): string => (string) $item->getGroup());

        $children = $groups
            ->map(function (string $group) use ($itemsByGroup): ?NavigationItem {
                $items = $itemsByGroup->get(WebsiteNavigationGroups::label($group));

                if ($items === null || $items->isEmpty()) {
                    return null;
                }

                return NavigationItem::make(WebsiteNavigationGroups::label($group))
                    ->icon(WebsiteNavigationGroups::icon($group))
                    ->sort(self::groupSort($group))
                    ->childItems(
                        $items->sortBy(fn (NavigationItem $item): int => $item->getSort())->values()->all(),
                    );
            })
            ->filter()
            ->values()
            ->all();

        return array_map(
            fn (NavigationItem $item): NavigationItem => $item->childItems($children),
            parent::getNavigationItems(),
        );
    }

    private static function groupSort(string $group): int
    {
        $index = array_search($group, WebsiteNavigationGroups::all(), true);

        // `=== false` y no `?: null`: el orden del prototipo empieza en 0.
        return $index === false ? 99 : $index + 1;
    }

    /**
     * El submenú del clúster **no** se dibuja al costado del contenido: sus ítems viven ahora en el
     * sidebar, así que dibujarlo también en la página sería el mismo menú dos veces.
     */
    protected static bool $shouldRegisterSubNavigation = false;

    /**
     * WU-S1: el submenú va al **sidebar**, agrupado, en vez de la barra de pestañas horizontal
     * con scroll de arriba. Un clúster agrupa sus ítems por el `$navigationGroup` de cada
     * componente, así que los cuatro grupos del prototipo (Publicación, Contenido, Ajustes,
     * Bandeja) salen de ahí sin inventar un componente de navegación propio.
     */
    protected static ?\Filament\Pages\Enums\SubNavigationPosition $subNavigationPosition = \Filament\Pages\Enums\SubNavigationPosition::Start;
}
