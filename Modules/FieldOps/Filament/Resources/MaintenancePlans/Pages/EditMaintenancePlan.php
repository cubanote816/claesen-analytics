<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Resources\MaintenancePlans\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\FieldOps\Filament\Resources\FoMaintenancePlanResource;

class EditMaintenancePlan extends EditRecord
{
    protected static string $resource = FoMaintenancePlanResource::class;
}
