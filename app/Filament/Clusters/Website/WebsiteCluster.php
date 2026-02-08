<?php

namespace App\Filament\Clusters\Website;

use BackedEnum;
use Filament\Clusters\Cluster;
use UnitEnum;

class WebsiteCluster extends Cluster
{
    protected static BackedEnum | string | null $navigationIcon = 'heroicon-o-globe-alt';

    public static function getNavigationLabel(): string
    {
        return __('website.cluster_label');
    }

    protected static UnitEnum | string | null $navigationGroup = 'Content Management';

    protected static ?\Filament\Pages\Enums\SubNavigationPosition $subNavigationPosition = \Filament\Pages\Enums\SubNavigationPosition::Top;
}
