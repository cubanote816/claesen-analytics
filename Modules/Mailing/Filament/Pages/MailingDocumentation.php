<?php

declare(strict_types=1);

namespace Modules\Mailing\Filament\Pages;

use App\Filament\Documentation\ModuleDocumentationPage;
use Modules\Mailing\Filament\Resources\CampaignResource;

/**
 * Mailing user guide (CLA-693). Content: Modules/Mailing/docs/user-guide/{en,nl}.md.
 * Access follows the campaign resource: everyone except the technician role.
 */
class MailingDocumentation extends ModuleDocumentationPage
{
    protected static ?int $navigationSort = 1;

    public static function module(): string
    {
        return 'Mailing';
    }

    public static function accessFollows(): string
    {
        return CampaignResource::class;
    }
}
