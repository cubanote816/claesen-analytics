<?php

declare(strict_types=1);

namespace Tests\Fixtures\DocumentationPanel\Pages;

use Filament\Resources\Pages\CreateRecord;
use Tests\Fixtures\DocumentationPanel\GadgetResource;

class CreateGadget extends CreateRecord
{
    protected static string $resource = GadgetResource::class;
}
