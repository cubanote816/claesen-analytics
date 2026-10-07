<?php

namespace App\Filament\Clusters\Website;

use BackedEnum;
use Filament\Clusters\Cluster;
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
     * WU-S1: el submenú va al **sidebar**, agrupado, en vez de la barra de pestañas horizontal
     * con scroll de arriba. Un clúster agrupa sus ítems por el `$navigationGroup` de cada
     * componente, así que los cuatro grupos del prototipo (Publicación, Contenido, Ajustes,
     * Bandeja) salen de ahí sin inventar un componente de navegación propio.
     */
    protected static ?\Filament\Pages\Enums\SubNavigationPosition $subNavigationPosition = \Filament\Pages\Enums\SubNavigationPosition::Start;
}
