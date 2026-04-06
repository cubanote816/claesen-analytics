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

    protected static ?\Filament\Pages\Enums\SubNavigationPosition $subNavigationPosition = \Filament\Pages\Enums\SubNavigationPosition::Top;
}
