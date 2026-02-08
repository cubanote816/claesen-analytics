<?php

namespace Modules\Website\App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProjectCategory: string implements HasLabel
{
    case SPORT = 'sport';
    case INDUSTRIAL = 'industrial';
    case PUBLIC = 'public';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SPORT => 'Sport',
            self::INDUSTRIAL => 'Industrial',
            self::PUBLIC => 'Public',
        };
    }
}
