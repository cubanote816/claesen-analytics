<?php

namespace Modules\Mailing\Filament\Resources\EmailTemplates\Pages;

use Modules\Mailing\Filament\Resources\EmailTemplates\EmailTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmailTemplate extends CreateRecord
{
    protected static string $resource = EmailTemplateResource::class;
}
