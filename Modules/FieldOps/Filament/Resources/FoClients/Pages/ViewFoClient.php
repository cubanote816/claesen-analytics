<?php

namespace Modules\FieldOps\Filament\Resources\FoClients\Pages;

use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\FieldOps\Filament\Resources\FoClientResource;

class ViewFoClient extends ViewRecord
{
    protected static string $resource = FoClientResource::class;

    // Filament's default ViewRecord::getTitle() wraps getRecordTitle() in a
    // "View :label" translation string — the record's own name is the point of
    // this page, not the name of the CRUD action, so the wrapper is skipped here.
    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }
}
