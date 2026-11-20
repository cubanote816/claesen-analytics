<?php

declare(strict_types=1);

namespace Tests\Fixtures\DocumentationPanel\Pages;

use Filament\Resources\Pages\ListRecords;
use Tests\Fixtures\DocumentationPanel\GadgetResource;

class ListGadgets extends ListRecords
{
    protected static string $resource = GadgetResource::class;
}
