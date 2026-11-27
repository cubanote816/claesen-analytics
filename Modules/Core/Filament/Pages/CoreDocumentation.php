<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages;

use App\Filament\Documentation\ModuleDocumentationPage;
use Modules\Core\Filament\Resources\Users\UserResource;

/**
 * Users and access guide (CLA-719). Content: Modules/Core/docs/user-guide/{en,nl}.md.
 * Access follows the users resource (user decision): super admins only.
 */
class CoreDocumentation extends ModuleDocumentationPage
{
    protected static ?int $navigationSort = 2;

    public static function module(): string
    {
        return 'Core';
    }

    public static function accessFollows(): string
    {
        return UserResource::class;
    }
}
