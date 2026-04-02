<?php

namespace Modules\Cafca\Filament\Resources\Employees\Pages;

use Modules\Cafca\Filament\Resources\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;
}
