<?php

declare(strict_types=1);

namespace Tests\Fixtures\DocumentationPanel\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Tests\Fixtures\DocumentationPanel\GadgetResource;

/** CLA-728 fixture: the page overrides the resource infolist with its own action row. */
class ViewGadget extends ViewRecord
{
    protected static string $resource = GadgetResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([Actions::make([Action::make('audit')])]);
    }
}
