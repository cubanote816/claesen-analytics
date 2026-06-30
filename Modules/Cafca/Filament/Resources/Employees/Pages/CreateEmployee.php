<?php

namespace Modules\Cafca\Filament\Resources\Employees\Pages;

use Modules\Employee\Filament\Resources\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;
}
