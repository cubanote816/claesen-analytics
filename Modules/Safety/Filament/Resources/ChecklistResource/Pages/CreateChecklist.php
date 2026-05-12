<?php

namespace Modules\Safety\Filament\Resources\ChecklistResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Modules\Safety\Filament\Resources\ChecklistResource;

class CreateChecklist extends CreateRecord
{
    protected static string $resource = ChecklistResource::class;
}
